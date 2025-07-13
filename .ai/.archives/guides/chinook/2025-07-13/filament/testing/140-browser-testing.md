# Browser Testing Guide

This guide covers comprehensive cross-browser compatibility testing for the Chinook Filament admin panel using Laravel
Dusk, including end-to-end testing, browser automation, and cross-platform validation.

## Table of Contents

- [Overview](#overview)
- [Laravel Dusk Setup](#laravel-dusk-setup)
- [Basic Browser Testing](#basic-browser-testing)
- [Cross-Browser Testing](#cross-browser-testing)
- [User Interface Testing](#user-interface-testing)
- [Form Interaction Testing](#form-interaction-testing)
- [JavaScript Functionality Testing](#javascript-functionality-testing)
- [Mobile Responsiveness Testing](#mobile-responsiveness-testing)
- [Performance Testing](#performance-testing)

## Overview

Browser testing ensures the Chinook admin panel works correctly across different browsers, devices, and screen sizes.
This includes testing user interactions, JavaScript functionality, and visual consistency.

### Testing Objectives

- **Cross-Browser Compatibility**: Ensure functionality across major browsers
- **User Experience**: Validate smooth user interactions and workflows
- **Responsive Design**: Test mobile and tablet compatibility
- **JavaScript Functionality**: Verify client-side features work correctly
- **Visual Consistency**: Ensure UI elements render properly

## Laravel Dusk Setup

### Installation and Configuration

```bash
# Install Laravel Dusk
composer require --dev laravel/dusk

# Install Dusk
php artisan dusk:install

# Install Chrome driver
php artisan dusk:chrome-driver

# Create test environment file
cp .env .env.dusk.local
```

### Dusk Configuration

```php
// tests/DuskTestCase.php
<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver();
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless', // Remove for visual debugging
        ])->unless($this->hasHeadlessDisabled(), function ($items) {
            return $items->forget(collect($items)->search('--headless'));
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
               isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    /**
     * Determine if the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
               isset($_ENV['DUSK_START_MAXIMIZED']);
    }
}
```

### Test Environment Configuration

```bash
# .env.dusk.local
APP_ENV=testing
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

## Basic Browser Testing

### Authentication Testing

```php
// tests/Browser/AuthenticationTest.php
<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('Admin');

        $this->browse(function (Browser $browser) {
            $browser->visit('/chinook-admin/login')
                    ->type('email', 'admin@test.com')
                    ->type('password', 'password123')
                    ->press('Login')
                    ->assertPathIs('/chinook-admin')
                    ->assertSee('Dashboard');
        });
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/chinook-admin/login')
                    ->type('email', 'invalid@test.com')
                    ->type('password', 'wrongpassword')
                    ->press('Login')
                    ->assertPathIs('/chinook-admin/login')
                    ->assertSee('These credentials do not match our records');
        });
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/chinook-admin')
                    ->click('@user-menu')
                    ->click('@logout-button')
                    ->assertPathIs('/chinook-admin/login')
                    ->assertSee('Login');
        });
    }

    public function test_remember_me_functionality(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole('Admin');

        $this->browse(function (Browser $browser) {
            $browser->visit('/chinook-admin/login')
                    ->type('email', 'admin@test.com')
                    ->type('password', 'password123')
                    ->check('remember')
                    ->press('Login')
                    ->assertPathIs('/chinook-admin');

            // Verify remember cookie is set
            $cookies = $browser->driver->manage()->getCookies();
            $rememberCookie = collect($cookies)->firstWhere('name', 'like', 'remember_%');
            $this->assertNotNull($rememberCookie);
        });
    }
}
```

### Navigation Testing

```php
public function test_main_navigation_works(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin')
                ->assertSee('Dashboard')
                ->click('@nav-artists')
                ->assertPathIs('/chinook-admin/artists')
                ->assertSee('Artists')
                ->click('@nav-albums')
                ->assertPathIs('/chinook-admin/albums')
                ->assertSee('Albums')
                ->click('@nav-tracks')
                ->assertPathIs('/chinook-admin/tracks')
                ->assertSee('Tracks');
    });
}

public function test_breadcrumb_navigation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $artist = Artist::factory()->create();

    $this->browse(function (Browser $browser) use ($user, $artist) {
        $browser->loginAs($user)
                ->visit("/chinook-admin/artists/{$artist->id}")
                ->assertSee('Artists')
                ->assertSee($artist->name)
                ->click('@breadcrumb-artists')
                ->assertPathIs('/chinook-admin/artists');
    });
}
```

## Cross-Browser Testing

### Multi-Browser Test Configuration

```php
// tests/Browser/CrossBrowserTest.php
<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CrossBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test across multiple browsers
     */
    public function test_artist_creation_across_browsers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        // Test in Chrome
        $this->browse(function (Browser $browser) use ($user) {
            $this->testArtistCreation($browser, $user, 'Chrome');
        });

        // Test in Firefox (if configured)
        if ($this->hasFirefoxDriver()) {
            $this->browseInFirefox(function (Browser $browser) use ($user) {
                $this->testArtistCreation($browser, $user, 'Firefox');
            });
        }
    }

    private function testArtistCreation(Browser $browser, User $user, string $browserName): void
    {
        $artistName = "Test Artist ({$browserName})";

        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->click('@create-artist')
                ->assertPathIs('/chinook-admin/artists/create')
                ->type('name', $artistName)
                ->select('country', 'US')
                ->type('biography', 'Test biography')
                ->press('Create')
                ->assertPathIs('/chinook-admin/artists')
                ->assertSee($artistName);
    }

    private function hasFirefoxDriver(): bool
    {
        return file_exists(base_path('vendor/laravel/dusk/bin/geckodriver-linux'));
    }

    private function browseInFirefox(\Closure $callback): void
    {
        // Firefox driver configuration would go here
        // This is a simplified example
        $callback($this->newBrowser());
    }
}
```

### Browser Compatibility Testing

```php
public function test_css_grid_support(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertPresent('.grid-container')
                ->script('return getComputedStyle(document.querySelector(".grid-container")).display === "grid"');
        
        $this->assertTrue($browser->script('return CSS.supports("display", "grid")')[0]);
    });
}

public function test_flexbox_layout(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin')
                ->assertPresent('.flex-container');
        
        $flexSupport = $browser->script('return CSS.supports("display", "flex")')[0];
        $this->assertTrue($flexSupport);
    });
}
```

## User Interface Testing

### Form Interaction Testing

```php
public function test_artist_form_interactions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->assertSee('Create Artist')
                ->type('name', 'Interactive Test Artist')
                ->select('country', 'US')
                ->type('website', 'https://example.com')
                ->type('formed_year', '2020')
                ->check('is_active')
                ->press('Create')
                ->assertPathIs('/chinook-admin/artists')
                ->assertSee('Interactive Test Artist');
    });
}

public function test_form_validation_display(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->press('Create') // Submit without required fields
                ->assertSee('The name field is required')
                ->assertSee('The country field is required')
                ->assertPresent('.error-message');
    });
}

public function test_dynamic_form_fields(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->click('@add-social-link')
                ->assertPresent('@social-link-0')
                ->select('@social-link-0-platform', 'facebook')
                ->type('@social-link-0-url', 'https://facebook.com/artist')
                ->click('@add-social-link')
                ->assertPresent('@social-link-1')
                ->click('@remove-social-link-0')
                ->assertMissing('@social-link-0');
    });
}
```

### Table Interaction Testing

```php
public function test_table_sorting(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->create(['name' => 'Alpha Artist']);
    Artist::factory()->create(['name' => 'Beta Artist']);
    Artist::factory()->create(['name' => 'Gamma Artist']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->click('@sort-name-asc')
                ->waitFor('@table-row-0')
                ->assertSeeIn('@table-row-0', 'Alpha Artist')
                ->click('@sort-name-desc')
                ->waitFor('@table-row-0')
                ->assertSeeIn('@table-row-0', 'Gamma Artist');
    });
}

public function test_table_filtering(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->create(['name' => 'Rock Artist', 'country' => 'US']);
    Artist::factory()->create(['name' => 'Jazz Artist', 'country' => 'CA']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->type('@search-input', 'Rock')
                ->keys('@search-input', '{enter}')
                ->waitFor('@table-row-0')
                ->assertSee('Rock Artist')
                ->assertDontSee('Jazz Artist')
                ->clear('@search-input')
                ->select('@country-filter', 'CA')
                ->waitFor('@table-row-0')
                ->assertSee('Jazz Artist')
                ->assertDontSee('Rock Artist');
    });
}

public function test_table_pagination(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->count(30)->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertSee('Next')
                ->click('@next-page')
                ->waitForLocation('/chinook-admin/artists?page=2')
                ->assertSee('Previous')
                ->click('@previous-page')
                ->waitForLocation('/chinook-admin/artists?page=1');
    });
}
```

## Form Interaction Testing

### Complex Form Testing

```php
public function test_multi_step_form(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->assertSee('Step 1: Basic Information')
                ->type('name', 'Multi-step Artist')
                ->select('country', 'US')
                ->click('@next-step')
                ->assertSee('Step 2: Additional Details')
                ->type('biography', 'Artist biography')
                ->type('website', 'https://example.com')
                ->click('@next-step')
                ->assertSee('Step 3: Categories')
                ->check('@category-rock')
                ->check('@category-alternative')
                ->click('@finish')
                ->assertPathIs('/chinook-admin/artists')
                ->assertSee('Multi-step Artist');
    });
}

public function test_file_upload_functionality(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->type('name', 'Upload Test Artist')
                ->select('country', 'US')
                ->attach('@profile-image', __DIR__.'/fixtures/test-image.jpg')
                ->waitFor('@image-preview')
                ->assertPresent('@image-preview')
                ->press('Create')
                ->assertPathIs('/chinook-admin/artists')
                ->assertSee('Upload Test Artist');
    });
}

public function test_autocomplete_functionality(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    $existingArtist = Artist::factory()->create(['name' => 'Existing Artist']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/albums/create')
                ->type('@artist-search', 'Exist')
                ->waitFor('@autocomplete-dropdown')
                ->assertSee('Existing Artist')
                ->click('@autocomplete-option-0')
                ->assertInputValue('@artist-search', 'Existing Artist');
    });
}
```

### Modal and Dialog Testing

```php
public function test_confirmation_modal(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $artist = Artist::factory()->create();

    $this->browse(function (Browser $browser) use ($user, $artist) {
        $browser->loginAs($user)
                ->visit("/chinook-admin/artists/{$artist->id}")
                ->click('@delete-button')
                ->waitFor('@confirmation-modal')
                ->assertSee('Are you sure you want to delete this artist?')
                ->click('@cancel-button')
                ->assertMissing('@confirmation-modal')
                ->click('@delete-button')
                ->waitFor('@confirmation-modal')
                ->click('@confirm-delete')
                ->waitUntilMissing('@confirmation-modal')
                ->assertPathIs('/chinook-admin/artists')
                ->assertDontSee($artist->name);
    });
}

public function test_modal_form_submission(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $artist = Artist::factory()->create();

    $this->browse(function (Browser $browser) use ($user, $artist) {
        $browser->loginAs($user)
                ->visit("/chinook-admin/artists/{$artist->id}")
                ->click('@quick-edit-button')
                ->waitFor('@edit-modal')
                ->clear('@modal-name-input')
                ->type('@modal-name-input', 'Quick Edited Name')
                ->click('@modal-save-button')
                ->waitUntilMissing('@edit-modal')
                ->assertSee('Quick Edited Name');
    });
}
```

## JavaScript Functionality Testing

### AJAX and Dynamic Content Testing

```php
public function test_ajax_search_functionality(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->create(['name' => 'Searchable Artist']);
    Artist::factory()->create(['name' => 'Another Artist']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->type('@live-search', 'Search')
                ->waitFor('@search-results')
                ->assertSee('Searchable Artist')
                ->assertDontSee('Another Artist')
                ->clear('@live-search')
                ->waitFor('@search-results')
                ->assertSee('Searchable Artist')
                ->assertSee('Another Artist');
    });
}

public function test_infinite_scroll(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->count(50)->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists?view=infinite')
                ->assertPresent('@artist-list')
                ->scrollToBottom()
                ->waitFor('@loading-indicator')
                ->waitUntilMissing('@loading-indicator')
                ->assertPresent('@load-more-content');
    });
}

public function test_real_time_updates(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/dashboard')
                ->assertSee('Total Artists: 0')
                ->script('
                    // Simulate real-time update
                    window.Echo.channel("admin-updates")
                        .listen("ArtistCreated", (e) => {
                            document.querySelector("#artist-count").textContent = e.count;
                        });
                ');

        // Create artist in background and verify update
        Artist::factory()->create();
        
        $browser->waitForText('Total Artists: 1');
    });
}
```

### Client-Side Validation Testing

```php
public function test_client_side_form_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->type('website', 'invalid-url')
                ->click('body') // Trigger blur event
                ->waitFor('@website-error')
                ->assertSee('Please enter a valid URL')
                ->clear('website')
                ->type('website', 'https://valid-url.com')
                ->click('body')
                ->waitUntilMissing('@website-error');
    });
}
```

## Mobile Responsiveness Testing

### Mobile Device Testing

```php
public function test_mobile_navigation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->resize(375, 667) // iPhone SE size
                ->loginAs($user)
                ->visit('/chinook-admin')
                ->assertMissing('@desktop-nav')
                ->click('@mobile-menu-toggle')
                ->waitFor('@mobile-nav')
                ->assertSee('Artists')
                ->assertSee('Albums')
                ->click('@mobile-nav-artists')
                ->assertPathIs('/chinook-admin/artists');
    });
}

public function test_responsive_table(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Artist::factory()->count(5)->create();

    $this->browse(function (Browser $browser) use ($user) {
        // Desktop view
        $browser->resize(1200, 800)
                ->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertPresent('@desktop-table')
                ->assertMissing('@mobile-cards');

        // Mobile view
        $browser->resize(375, 667)
                ->refresh()
                ->assertMissing('@desktop-table')
                ->assertPresent('@mobile-cards');
    });
}

public function test_touch_interactions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->resize(375, 667)
                ->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->tap('@mobile-card-0') // Touch interaction
                ->assertPresent('@card-actions')
                ->swipeLeft('@mobile-card-0') // Swipe gesture
                ->assertPresent('@swipe-actions');
    });
}
```

## Performance Testing

### Page Load Performance

```php
public function test_page_load_performance(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user);

        $startTime = microtime(true);
        
        $browser->visit('/chinook-admin/artists');
        
        $endTime = microtime(true);
        $loadTime = $endTime - $startTime;

        $this->assertLessThan(3.0, $loadTime, 
            "Page load took {$loadTime} seconds");
    });
}

public function test_javascript_performance(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->script('
                    performance.mark("start-search");
                    document.querySelector("#search-input").value = "test";
                    document.querySelector("#search-input").dispatchEvent(new Event("input"));
                ')
                ->waitFor('@search-results')
                ->script('
                    performance.mark("end-search");
                    performance.measure("search-duration", "start-search", "end-search");
                ');

        $searchDuration = $browser->script('
            return performance.getEntriesByName("search-duration")[0].duration;
        ')[0];

        $this->assertLessThan(500, $searchDuration, 
            "Search took {$searchDuration}ms");
    });
}
```

## Related Documentation

- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[Accessibility Testing](150-accessibility-testing.md)** - WCAG compliance testing
- **[Security Testing](160-security-testing.md)** - Security validation
- **[Form Testing](060-form-testing.md)** - Form validation testing

---

## Navigation

**← Previous:** [Performance Testing](130-performance-testing.md)

**Next →** [Accessibility Testing](150-accessibility-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
