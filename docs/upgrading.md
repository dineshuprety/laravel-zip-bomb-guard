# Upgrade from 1.x to 2.x

Version 2 moves the PHP namespace from `DineshUprety\ZipBombGuard\` to the shared project namespace `ZipBombGuard\`. This is a breaking API change: the old classes and trait are not aliased. The Composer package is still `dineshuprety/laravel-zip-bomb-guard`; no repository migration is needed.

## Update application references

Replace the old prefix in `use` statements, fully qualified names, PHPDoc, container bindings, mocks, and any explicit Laravel provider or facade registration. Check string-based class references as well as PHP imports.

| 1.x | 2.x |
| --- | --- |
| `DineshUprety\ZipBombGuard\ArchiveSafetyChecker` | `ZipBombGuard\ArchiveSafetyChecker` |
| `DineshUprety\ZipBombGuard\Concerns\SafeExtractsZips` | `ZipBombGuard\Concerns\SafeExtractsZips` |
| `DineshUprety\ZipBombGuard\Rules\NoZipBomb` | `ZipBombGuard\Rules\NoZipBomb` |
| `DineshUprety\ZipBombGuard\Exceptions\UnsafeArchiveException` | `ZipBombGuard\Exceptions\UnsafeArchiveException` |
| `DineshUprety\ZipBombGuard\Facades\ZipBombGuard` | `ZipBombGuard\Facades\ZipBombGuard` |
| `DineshUprety\ZipBombGuard\ZipBombGuardServiceProvider` | `ZipBombGuard\ZipBombGuardServiceProvider` |
| `DineshUprety\ZipBombGuard\Console\ScanArchivesCommand` | `ZipBombGuard\Console\ScanArchivesCommand` |

For example:

```php
use ZipBombGuard\ArchiveSafetyChecker;
use ZipBombGuard\Exceptions\UnsafeArchiveException;

try {
    app(ArchiveSafetyChecker::class)->assertSafe($archivePath);
} catch (UnsafeArchiveException $exception) {
    // Reject the upload or stop the job.
}
```

The global Laravel facade alias `ZipBombGuard` still works. New examples use the explicit `ZipBombGuard\Facades\ZipBombGuard` import.

## Upgrade and verify

Prepare code changes and the dependency upgrade together in a development or staging checkout before deploying them together:

```bash
composer require dineshuprety/laravel-zip-bomb-guard:^2.0
php artisan optimize:clear
php artisan package:discover
php artisan test
```

Applications using automatic discovery do not need to add a provider manually. If discovery is disabled or you registered the old provider explicitly, update that registration before Composer runs Laravel's discovery script. Rebuild any configuration caches used in deployment and restart long-running workers through your normal deployment process. Drain or otherwise handle queued jobs containing serialized objects from the old namespace before upgrading; renaming imports does not rewrite persisted job payloads.

Verify an accepted upload, a rejected archive, and your extraction or scan workflow in your application. Keep a fresh private extraction destination and the same resource limits as before.

## What stays compatible

The `no_zip_bomb` validation rule, `zip-bomb:scan` command, `zip-bomb-guard` configuration keys, config publishing tag, constructor/method arguments, and security checks retain their behavior. PHP and Laravel version requirements are unchanged. Author credits and the GitHub/Packagist account identify ownership; they are not part of the PHP API.

If you cannot migrate yet, keep your application's Composer constraint on `^1.0.1`. Published 1.x releases remain available with the original namespace.
