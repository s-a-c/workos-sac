# Real-time Updates Implementation

## Overview

This guide covers implementing real-time updates in the Chinook Filament 4 admin panel using Laravel Broadcasting, WebSockets, and Livewire for dynamic content updates without page refreshes.

## Table of Contents

- [Overview](#overview)
- [Broadcasting Setup](#broadcasting-setup)
- [WebSocket Configuration](#websocket-configuration)
- [Livewire Integration](#livewire-integration)
- [Real-time Widgets](#real-time-widgets)
- [Event Broadcasting](#event-broadcasting)
- [Frontend Implementation](#frontend-implementation)
- [Performance Optimization](#performance-optimization)
- [Security Considerations](#security-considerations)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Navigation](#navigation)

## Broadcasting Setup

### Package Installation

```bash
# Install required packages
composer require pusher/pusher-php-server
npm install --save-dev laravel-echo pusher-js

# Publish broadcasting configuration
php artisan vendor:publish --provider="Illuminate\Broadcasting\BroadcastServiceProvider"
```

### Environment Configuration

```env
# .env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# Alternative: Redis Broadcasting
BROADCAST_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Broadcasting Configuration

```php
// config/broadcasting.php
<?php

return [
    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],
];
```

## WebSocket Configuration

### Laravel WebSockets (Alternative to Pusher)

```bash
# Install Laravel WebSockets
composer require beyondcode/laravel-websockets

# Publish configuration
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="migrations"
php artisan migrate

php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"
```

### WebSocket Configuration

```php
// config/websockets.php
<?php

return [
    'dashboard' => [
        'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
    ],

    'apps' => [
        [
            'id' => env('PUSHER_APP_ID'),
            'name' => env('APP_NAME'),
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'path' => env('PUSHER_APP_PATH'),
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_statistics' => true,
        ],
    ],

    'app_provider' => BeyondCode\LaravelWebSockets\Apps\ConfigAppProvider::class,

    'allowed_origins' => [
        'localhost:3000',
        'chinook-admin.test',
    ],

    'max_request_size_in_kb' => 250,

    'path' => 'laravel-websockets',

    'middleware' => [
        'web',
        BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize::class,
    ],

    'statistics' => [
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,
        'logger' => BeyondCode\LaravelWebSockets\Statistics\Logger\HttpStatisticsLogger::class,
        'interval_in_seconds' => 60,
        'delete_statistics_older_than_days' => 60,
        'perform_dns_lookup' => false,
    ],

    'ssl' => [
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),
    ],

    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,
];
```

## Livewire Integration

### Real-time Component Base

```php
// app/Livewire/RealTimeComponent.php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;

abstract class RealTimeComponent extends Component
{
    public function getListeners()
    {
        return [
            "echo-private:admin.{$this->getChannelName()},ModelUpdated" => 'handleModelUpdated',
            "echo-private:admin.{$this->getChannelName()},ModelCreated" => 'handleModelCreated',
            "echo-private:admin.{$this->getChannelName()},ModelDeleted" => 'handleModelDeleted',
        ];
    }

    abstract protected function getChannelName(): string;

    #[On('handleModelUpdated')]
    public function handleModelUpdated($data)
    {
        $this->dispatch('model-updated', $data);
        $this->refreshData();
    }

    #[On('handleModelCreated')]
    public function handleModelCreated($data)
    {
        $this->dispatch('model-created', $data);
        $this->refreshData();
    }

    #[On('handleModelDeleted')]
    public function handleModelDeleted($data)
    {
        $this->dispatch('model-deleted', $data);
        $this->refreshData();
    }

    abstract protected function refreshData(): void;
}
```

### Real-time Dashboard Widget

```php
// app/Filament/Widgets/RealTimeSalesWidget.php
<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Track;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RealTimeSalesWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public function getStats(): array
    {
        return [
            Stat::make('Total Sales Today', $this->getTodaySales())
                ->description('Real-time sales tracking')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($this->getSalesChart()),

            Stat::make('Active Users', $this->getActiveUsers())
                ->description('Currently online')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Popular Tracks', $this->getPopularTracks())
                ->description('Most played today')
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('warning'),
        ];
    }

    #[On('echo-private:admin.sales,SaleCompleted')]
    public function handleSaleCompleted($data)
    {
        $this->dispatch('sale-completed', $data);
        // Refresh the widget data
        $this->dispatch('$refresh');
    }

    #[On('echo-private:admin.users,UserActivity')]
    public function handleUserActivity($data)
    {
        $this->dispatch('user-activity', $data);
        $this->dispatch('$refresh');
    }

    protected function getTodaySales(): string
    {
        $total = Invoice::whereDate('created_at', today())
            ->sum('total');
        
        return '$' . number_format($total, 2);
    }

    protected function getActiveUsers(): int
    {
        // Implementation depends on your user tracking system
        return cache()->remember('active_users_count', 60, function () {
            // Count users active in last 5 minutes
            return \App\Models\User::where('last_activity', '>=', now()->subMinutes(5))->count();
        });
    }

    protected function getPopularTracks(): int
    {
        return Track::whereHas('playlistTracks', function ($query) {
            $query->whereDate('created_at', today());
        })->count();
    }

    protected function getSalesChart(): array
    {
        // Return hourly sales data for the chart
        $hours = collect(range(0, 23))->map(function ($hour) {
            return Invoice::whereDate('created_at', today())
                ->whereHour('created_at', $hour)
                ->sum('total');
        });

        return $hours->toArray();
    }
}
```

## Real-time Widgets

### Live Data Table Widget

```php
// app/Filament/Widgets/LiveInvoicesWidget.php
<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class LiveInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->with(['customer'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) => 
                        $record->customer->first_name . ' ' . $record->customer->last_name
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),
            ])
            ->poll('5s') // Auto-refresh every 5 seconds
            ->striped();
    }

    #[On('echo-private:admin.invoices,InvoiceCreated')]
    public function handleInvoiceCreated($data)
    {
        $this->dispatch('invoice-created', $data);
        $this->dispatch('$refresh');
    }

    #[On('echo-private:admin.invoices,InvoiceUpdated')]
    public function handleInvoiceUpdated($data)
    {
        $this->dispatch('invoice-updated', $data);
        $this->dispatch('$refresh');
    }
}
```

## Event Broadcasting

### Model Events

```php
// app/Models/Invoice.php
<?php

namespace App\Models;

use App\Events\InvoiceCreated;
use App\Events\InvoiceUpdated;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $dispatchesEvents = [
        'created' => InvoiceCreated::class,
        'updated' => InvoiceUpdated::class,
    ];

    // ... rest of model
}
```

### Event Classes

```php
// app/Events/InvoiceCreated.php
<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.invoices'),
            new PrivateChannel('admin.sales'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'InvoiceCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->invoice->id,
            'total' => $this->invoice->total,
            'customer_name' => $this->invoice->customer->first_name . ' ' . $this->invoice->customer->last_name,
            'created_at' => $this->invoice->created_at->toISOString(),
        ];
    }
}
```

## Frontend Implementation

### Echo Configuration

```javascript
// resources/js/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusherapp.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/api/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                .then(response => {
                    callback(false, response.data);
                })
                .catch(error => {
                    callback(true, error);
                });
            }
        };
    },
});
```

### Real-time Notifications

```javascript
// resources/js/real-time-notifications.js
document.addEventListener('DOMContentLoaded', function() {
    // Listen for real-time notifications
    window.Echo.private('admin.notifications')
        .listen('NotificationSent', (e) => {
            showNotification(e.message, e.type);
        });

    // Listen for system alerts
    window.Echo.private('admin.alerts')
        .listen('SystemAlert', (e) => {
            showSystemAlert(e.message, e.severity);
        });

    function showNotification(message, type = 'info') {
        // Integration with Filament's notification system
        window.dispatchEvent(new CustomEvent('notify', {
            detail: {
                message: message,
                type: type
            }
        }));
    }

    function showSystemAlert(message, severity = 'warning') {
        // Show system-wide alerts
        const alertContainer = document.getElementById('system-alerts');
        if (alertContainer) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${severity}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);

            // Auto-remove after 10 seconds
            setTimeout(() => {
                alert.remove();
            }, 10000);
        }
    }
});
```

## Performance Optimization

### Channel Authorization Caching

```php
// app/Http/Controllers/BroadcastController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BroadcastController extends Controller
{
    public function authorize(Request $request)
    {
        $user = $request->user();
        $channel = $request->input('channel_name');

        // Cache authorization for 5 minutes
        $cacheKey = "broadcast_auth:{$user->id}:{$channel}";
        
        return Cache::remember($cacheKey, 300, function () use ($user, $channel) {
            return $this->authorizeChannel($user, $channel);
        });
    }

    protected function authorizeChannel($user, $channel): bool
    {
        // Admin channels require admin role
        if (str_starts_with($channel, 'private-admin.')) {
            return $user->hasRole('admin');
        }

        // User-specific channels
        if (str_starts_with($channel, "private-user.{$user->id}")) {
            return true;
        }

        return false;
    }
}
```

### Event Queuing

```php
// app/Events/InvoiceCreated.php
<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceCreated implements ShouldBroadcastNow, ShouldQueue
{
    public $queue = 'broadcasts';
    public $delay = 0;

    // ... rest of event class
}
```

## Security Considerations

### Channel Authorization

```php
// routes/channels.php
<?php

use Illuminate\Support\Facades\Broadcast;

// Admin channels - require admin role
Broadcast::channel('admin.{channel}', function ($user, $channel) {
    return $user->hasRole('admin');
});

// User-specific channels
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Public channels with rate limiting
Broadcast::channel('public.{channel}', function ($user, $channel) {
    // Rate limit public channel access
    return RateLimiter::attempt(
        "broadcast:public:{$user->id}",
        5, // 5 attempts
        function () {
            return true;
        },
        60 // per minute
    );
});
```

### Data Sanitization

```php
// app/Events/InvoiceCreated.php
public function broadcastWith(): array
{
    return [
        'id' => $this->invoice->id,
        'total' => number_format($this->invoice->total, 2),
        'customer_name' => e($this->invoice->customer->first_name . ' ' . $this->invoice->customer->last_name),
        'created_at' => $this->invoice->created_at->toISOString(),
        // Never broadcast sensitive data like payment details
    ];
}
```

## Testing

### Broadcasting Tests

```php
// tests/Feature/BroadcastingTest.php
<?php

namespace Tests\Feature;

use App\Events\InvoiceCreated;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_created_event_is_broadcasted()
    {
        Event::fake();

        $invoice = Invoice::factory()->create();

        Event::assertDispatched(InvoiceCreated::class, function ($event) use ($invoice) {
            return $event->invoice->id === $invoice->id;
        });
    }

    public function test_admin_can_access_admin_channels()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => 'test-socket',
                'channel_name' => 'private-admin.invoices'
            ])
            ->assertOk();
    }

    public function test_regular_user_cannot_access_admin_channels()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => 'test-socket',
                'channel_name' => 'private-admin.invoices'
            ])
            ->assertForbidden();
    }
}
```

## Troubleshooting

### Common Issues

1. **WebSocket Connection Failures**
   ```bash
   # Check WebSocket server status
   php artisan websockets:serve --port=6001
   
   # Verify firewall settings
   sudo ufw allow 6001
   ```

2. **Authentication Issues**
   ```php
   // Debug channel authorization
   Log::info('Channel authorization attempt', [
       'user_id' => $user->id,
       'channel' => $channel,
       'roles' => $user->roles->pluck('name')
   ]);
   ```

3. **Event Not Broadcasting**
   ```bash
   # Check queue workers
   php artisan queue:work --queue=broadcasts
   
   # Verify event configuration
   php artisan event:list
   ```

### Debug Commands

```bash
# Monitor WebSocket connections
php artisan websockets:serve --debug

# Test broadcasting
php artisan tinker
>>> broadcast(new App\Events\InvoiceCreated($invoice));

# Check queue status
php artisan queue:monitor broadcasts
```

## Navigation

### Related Documentation

- **[Dashboard Configuration](010-dashboard-configuration.md)** - Dashboard setup guide
- **[Widget Development](020-widget-development.md)** - Custom widget creation
- **[Chart Integration](030-chart-integration.md)** - Chart implementation
- **[Global Search](090-global-search.md)** - Search functionality

### External Resources

- **[Laravel Broadcasting](https://laravel.com/docs/12.x/broadcasting)** - Official Laravel broadcasting documentation
- **[Pusher Documentation](https://pusher.com/docs)** - Pusher WebSocket service
- **[Laravel WebSockets](https://beyondco.de/docs/laravel-websockets)** - Self-hosted WebSocket solution

---

**Last Updated**: 2025-07-07  
**Version**: 1.0.0  
**Compliance**: Laravel 12, Real-time best practices
