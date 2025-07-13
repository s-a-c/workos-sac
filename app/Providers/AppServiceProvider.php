<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Telescope\TelescopeServiceProvider;
use WorkOS\WorkOS;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCarbon();
        $this->configureCommands();
        $this->configureModels();
        $this->configurePasswordDefaults();
        $this->configurePulse();
        if (DB::getDriverName() === 'sqlite') {
            $this->configureSqliteDB();
        }
        $this->configureTelescope();
        $this->configureUrl();
        $this->configureVite();
        $this->configureWorkOS(); // Configure WorkOS SDK
    }

    /**
     * Configure the application's carbon.
     */
    private function configureCarbon(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->environment('production')
            && !$this->app->runningInConsole()
            && !$this->app->runningUnitTests()
            && !$this->app->isDownForMaintenance(),
        );
    }

    /**
     * Configure the application's models.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(!$this->app->environment('production'));
        Model::unguard(!$this->app->environment('production'));
    }

    /**
     * Configure the application's password defaults.
     * This method sets the default password requirements based on the environment.
     * If the application is in production, it enforces stricter password requirements.
     * In non-production environments, it relaxes the requirements.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            if ($this->app->environment('production')) {
                return $rule
                    ->min(12)
                    ->letters()
                    ->mixedcase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            return $rule;
        });
    }

    /**
     * Configure Pulse
     */
    private function configurePulse(): void
    {
        Gate::define('viewPulse', function (User $user) {
            return !$this->app->environment('production') || in_array($user->email, [
                    'embrace.s0ul@gmail.com',
                ]);
        });
    }

    /**
     * Configure the application's sqlite.
     */
    private function configureSqliteDB(): void
    {
        try {
            DB::unprepared(<<<'SQL'
                PRAGMA auto_vacuum = incremental;
                PRAGMA busy_timeout = 5000;
                PRAGMA cache_size = -64000;
                PRAGMA foreign_keys = ON;
                PRAGMA incremental_vacuum;
                PRAGMA journal_mode = WAL;
                PRAGMA mmap_size = 268435456;
                PRAGMA page_size = 32768;
                PRAGMA synchronous = NORMAL;
                PRAGMA temp_store = MEMORY;
                PRAGMA wal_autocheckpoint = 1000;
                SQL,
            );
        } catch (QueryException $e) {
            throw_unless(str_contains($e->getMessage(), 'does not exist.'), $e);
        }

    }

    /**
     * Configure Telescope
     */
    private function configureTelescope(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        Gate::define('viewTelescope', function (User $user) {
            return !$this->app->environment('production') || in_array($user->email, [
                    'embrace.s0ul@gmail.com',
                ]);
        });
    }

    /**
     * Configure the application's url.
     */
    private function configureUrl(): void
    {
        URL::forceScheme('https');
    }

    /**
     * Configure the application's vite.
     */
    private function configureVite(): void
    {
        Vite::useBuildDirectory('build')
            ->withEntryPoints([
                'resources/js/app.js',
            ]);
    }

    /**
     * Configure WorkOS SDK.
     */
    private function configureWorkOS(): void
    {
        if (config('services.workos.secret')) {
            WorkOS::setApiKey(config('services.workos.secret'));
        }

        if (config('services.workos.client_id')) {
            WorkOS::setClientId(config('services.workos.client_id'));
        }
    }
}
