# Usage guide

Start with [installation and requirements](../README.md#install). Run Artisan commands and application examples in your Laravel application, not in this package checkout.

## Choose an entry point

| Task | API | Result |
| --- | --- | --- |
| Validate an upload | `NoZipBomb` or `no_zip_bomb` | Adds a Laravel validation error when inspection fails |
| Inspect a local ZIP path | `$checker->assertSafe($path)` or `ZipBombGuard::assertSafe($path)` | Returns normally or throws `UnsafeArchiveException` |
| Obtain a boolean inspection result | `$checker->isSafe($path)` or `ZipBombGuard::isSafe($path)` | `false` when inspection throws `UnsafeArchiveException` |
| Extract into a local directory | `SafeExtractsZips::extractSafely()` | Inspects first, then streams the outer archive into the destination |
| Audit files on the server | `zip-bomb:scan` | Prints a result for each selected archive |

Resolve `$checker` with `app(ArchiveSafetyChecker::class)` and import the checker or facade class as shown in the [README](../README.md#usage). The facade, validation rules, and default extraction checker resolve the same configured Laravel singleton. A new top-level scan starts with fresh byte and entry budgets. Nested archive contents are inspected recursively; extraction does not recursively unpack them.

## Validate before processing an upload

Inside a controller action that receives an `Illuminate\Http\Request` named `$request`:

```php
use ZipBombGuard\Rules\NoZipBomb;

$request->validate([
    'archive' => ['required', 'file', 'mimes:zip', 'max:10240', new NoZipBomb],
]);
```

Here `max:10240` limits the uploaded file to 10 MiB. The guard's decompressed byte budget is separate. Apply your application's authentication, authorization, rate limits, and web-server/PHP upload limits as usual. PHP may already have written the upload to a temporary file before validation runs.

The string rule is interchangeable in this example: replace `new NoZipBomb` with `'no_zip_bomb'`. ZIP-based document types such as DOCX need the corresponding application MIME validation; the example intentionally accepts ZIP uploads only.

If a queue worker will process the file later, store it on private local storage first. An HTTP upload's temporary path is not a durable queue-job input. Remote storage objects must be downloaded to a controlled local path before using these APIs.

## Extract with cleanup on failure

The helper below accepts a local archive path and returns a new extraction directory. Call it from your application's service or job after applying its access rules:

```php
use ZipBombGuard\Concerns\SafeExtractsZips;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function extractZipForImport(string $archivePath): string
{
    $destination = storage_path('app/private/zip-imports/'.Str::uuid());

    if (! File::makeDirectory($destination, 0700, true)) {
        throw new RuntimeException('Could not create the import directory.');
    }

    $extractor = new class {
        use SafeExtractsZips;
    };

    try {
        $extractor->extractSafely($archivePath, $destination);

        return $destination;
    } catch (Throwable $exception) {
        File::deleteDirectory($destination);
        throw $exception;
    }
}
```

The trait removes the current partial file if streaming fails. Previously completed files may remain, which is why the example discards the entire job-owned directory on failure. Check and handle cleanup failures according to your application's storage policy. Remove successful extraction directories when the consuming job no longer needs them.

Keep the input archive and destination inaccessible to untrusted writers during the operation. The package does not make the separate inspection and extraction steps atomic against concurrent filesystem changes. Existing output files and symlink parent directories are rejected; retries should use a fresh destination.

The byte limit for streaming extraction comes from `zip-bomb-guard.max_total_bytes`. Passing a custom checker as the third argument customizes inspection but does not replace that Laravel configuration value.

## Scan from Artisan

```bash
php artisan zip-bomb:scan storage/app/private/uploads
php artisan zip-bomb:scan storage/app/private/uploads --fail-on-unsafe
php artisan zip-bomb:scan storage/app/private/example.zip --fail-on-unsafe
```

| Result | Default exit code | With `--fail-on-unsafe` |
| --- | --- | --- |
| All selected archives pass | 0 | 0 |
| At least one selected archive fails inspection | 0 | 1 |
| Supplied path does not exist | 1 | 1 |
| Directory contains no matching files | 0 | 0 |

Directory scans select lowercase `.zip`, `.jar`, `.war`, `.docx`, `.xlsx`, and `.pptx` filename patterns recursively. Passing an explicit file path sends that file to the checker regardless of its extension. A directory with no matches reports zero checked archives; that is not evidence that every file in the directory was inspected.

`SAFE` means the configured checks passed. It does not certify that a file is malware-free or that every ordinary file's contents were fully decompressed and integrity-checked. Review [configuration and limitations](configuration.md) before relying on a scan result.
