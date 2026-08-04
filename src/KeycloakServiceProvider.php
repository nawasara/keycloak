<?php

namespace Nawasara\Keycloak;

use Livewire\Livewire;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Nawasara\Keycloak\Console\Commands\SyncCommand;
use Nawasara\Keycloak\Jobs\Client\SyncKeycloakClientsJob;
use Nawasara\Keycloak\Jobs\User\SyncKeycloakUsersJob;
use Nawasara\Keycloak\Services\KeycloakClient;

class KeycloakServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Commands didaftarkan duluan — sebelum operasi lain yang mungkin gagal.
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-keycloak');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerLivewire();

        // Scope didaftarkan SEBELUM routes: UI token memfilter scope yang tidak
        // ter-register, jadi scope yang belum terdaftar tidak bisa di-assign
        // ke token sama sekali.
        $this->registerApiScopes();
        $this->registerApiRoutes();

        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            // Skip kalau scheduler dimatikan — mis. deployment tanpa
            // kredensial Keycloak admin, di mana task ini cuma akan gagal.
            if (! config('nawasara-keycloak.scheduler.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            // Sync users + clients tiap jam. User/client list relatif stabil;
            // sync user bisa lama (pagination, job timeout 600s) — hourly
            // cukup tanpa membebani Keycloak admin API.
            //
            // Dispatch job langsung lewat $schedule->call() — TIDAK lewat
            // $schedule->command('keycloak:sync'). Console command yang
            // didaftarkan via $this->commands() tidak selalu surface di
            // Artisan kernel (paket yang boot belakangan), jadi
            // $schedule->command() bisa gagal "namespace not defined".
            // $schedule->call() jalan di proses scheduler sendiri — tidak
            // butuh command terdaftar.
            $schedule->call(function () {
                SyncKeycloakUsersJob::dispatch(triggerSource: 'scheduled');
                SyncKeycloakClientsJob::dispatch(triggerSource: 'scheduled');
            })
                ->name('nawasara-keycloak:sync')
                ->hourly()
                ->withoutOverlapping(30);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-keycloak.php', 'nawasara-keycloak');

        $this->app->singleton(KeycloakClient::class, fn () => new KeycloakClient());
    }

    /**
     * Daftarkan scope API ke registry terpusat `nawasara/api`.
     *
     * Guard class_exists, bukan dependency composer — package ini tetap jalan
     * penuh tanpa nawasara/api terpasang; API-nya saja yang absen. Ini pola
     * yang sama dipakai cctv/secscan/wifi.
     */
    public function registerApiScopes(): void
    {
        if (! class_exists(\Nawasara\Api\Support\ScopeRegistry::class)) {
            return;
        }

        $registry = $this->app->make(\Nawasara\Api\Support\ScopeRegistry::class);

        $registry->register(
            'keycloak.user.read',
            'Direktori pegawai: cari + detail (username, nama, NIP, email, status aktif). '
            .'Untuk aplikasi lain yang perlu mengisi data pegawai otomatis. '
            .'Nomor WhatsApp, sesi, dan status 2FA tidak termasuk.',
        );
    }

    /**
     * Mount routes/api.php di prefix /api/v1/keycloak.
     *
     * Pakai Route::prefix()->group(), bukan loadRoutesFrom() — supaya prefix,
     * middleware group, dan name prefix bisa diterapkan sekaligus.
     */
    public function registerApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/keycloak';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.auth', 'api.log'])
            ->name('nawasara-api.keycloak.')
            ->group(__DIR__.'/../routes/api.php');
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Keycloak\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-keycloak.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }
}
