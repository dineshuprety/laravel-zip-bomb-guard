<?php

namespace DineshUprety\ZipBombGuard;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class ZipBombGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zip-bomb-guard.php', 'zip-bomb-guard');

        $this->app->singleton(ArchiveSafetyChecker::class, function () {
            return new ArchiveSafetyChecker(
                maxTotalBytes: (int) config('zip-bomb-guard.max_total_bytes'),
                maxFiles: (int) config('zip-bomb-guard.max_files'),
                maxRatio: (int) config('zip-bomb-guard.max_ratio'),
                maxNestingDepth: (int) config('zip-bomb-guard.max_nesting_depth'),
                rejectEncrypted: (bool) config('zip-bomb-guard.reject_encrypted'),
                rejectSymlinks: (bool) config('zip-bomb-guard.reject_symlinks'),
                blockedExtensions: (array) config('zip-bomb-guard.blocked_extensions'),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/zip-bomb-guard.php' => config_path('zip-bomb-guard.php'),
        ], 'zip-bomb-guard-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ScanArchivesCommand::class,
            ]);
        }

        // String rule: 'archive' => 'required|file|mimes:zip|no_zip_bomb'
        Validator::extend('no_zip_bomb', function ($attribute, $value) {
            $path = $value instanceof UploadedFile
                ? $value->getRealPath()
                : (is_string($value) ? $value : null);

            if (! is_string($path) || ! is_file($path)) {
                return false;
            }

            return app(ArchiveSafetyChecker::class)->isSafe($path);
        });

        Validator::replacer('no_zip_bomb', function ($message, $attribute) {
            return "The {$attribute} archive was rejected: it looks like a zip bomb or an unsafe archive.";
        });
    }
}
