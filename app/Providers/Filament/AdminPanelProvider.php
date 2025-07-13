<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ->login()    // use WorkOS authentication
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                // Standard Laravel middleware
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // AuthenticateSession::class,  // Omitted for WorkOS
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,

                // FilamentPHP middleware
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,

                // WorkOS middleware
                ValidateSessionWithWorkOS::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseTransactions()
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\Filament\Admin\Clusters')
            ->maxContentWidth(Width::Full)
            // ->profile()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->sidebarWidth('13rem')
            ->spa()
            ->strictAuthorization()
            ->subNavigationPosition(SubNavigationPosition::Top)
            // ->topNavigation()
            ->unsavedChangesAlerts()
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
