# Configuration and troubleshooting

Publish the configuration from your Laravel application:

```bash
php artisan vendor:publish --tag=zip-bomb-guard-config
```

The package uses the defaults in [config/zip-bomb-guard.php](../config/zip-bomb-guard.php) when you do not publish a file.

## Limits and units

| Key | Default | Environment variable | Scope |
| --- | --- | --- | --- |
| `max_total_bytes` | 104857600 (100 MiB) | `ZIP_BOMB_GUARD_MAX_BYTES` | Sum of declared sizes throughout one archive tree; also the streaming extraction ceiling |
| `max_files` | 1000 | `ZIP_BOMB_GUARD_MAX_FILES` | All entries throughout one archive tree, including directories and nested ZIP entries |
| `max_ratio` | 20 | `ZIP_BOMB_GUARD_MAX_RATIO` | Per-entry uncompressed/compressed ratio when compressed size is greater than zero |
| `max_nesting_depth` | 3 | `ZIP_BOMB_GUARD_MAX_NESTING` | Nested levels; the outer archive is depth 0, and 0 rejects any detected nested archive |
| `reject_encrypted` | true | `ZIP_BOMB_GUARD_REJECT_ENCRYPTED` | Reject encrypted entries before inspecting their content |
| `reject_symlinks` | true | `ZIP_BOMB_GUARD_REJECT_SYMLINKS` | Reject ZIP entries marked as Unix symlinks |
| `blocked_extensions` | php, phtml, phar, sh, exe, bat, cmd, com, scr | None; edit the published PHP array | Case-insensitive extension blocklist |

Use positive integers for byte, entry, and ratio limits, and a non-negative integer for nesting depth. Disabling encryption rejection permits content the checker cannot decrypt or inspect. Disabling the archive symlink check does not disable the extraction trait's destination-path checks.

For example, these application `.env` values set a 25 MiB archive-tree budget and 200-entry ceiling:

```dotenv
ZIP_BOMB_GUARD_MAX_BYTES=26214400
ZIP_BOMB_GUARD_MAX_FILES=200
ZIP_BOMB_GUARD_MAX_RATIO=20
ZIP_BOMB_GUARD_MAX_NESTING=3
ZIP_BOMB_GUARD_REJECT_ENCRYPTED=true
ZIP_BOMB_GUARD_REJECT_SYMLINKS=true
```

The byte value describes decompressed entries, not the HTTP upload size. Configure the latter in your application, PHP, and web server separately.

Rebuild Laravel's configuration cache when deploying configuration changes. Restart long-running workers so they resolve a checker with the updated settings. The checker is a singleton: changing configuration after it has been resolved does not rebuild that existing instance.

## How nested budgets work

An outer ZIP containing two ZIPs with 10 files each consumes 22 entries: two containers plus 20 inner files. Explicit directory entries also count. If each inner ZIP is 1 MiB and each expands to 8 MiB of files, the combined byte budget is 18 MiB: 2 MiB of ZIP containers plus 16 MiB of contents.

The budgets are shared by siblings and descendants, and reset for each top-level `assertSafe()` or `isSafe()` call. Temporary nested streams cannot grow beyond the declared size already charged to the budget. Lowering the depth limit rejects deeper archives; it does not silently skip them.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Composer cannot find the package | Confirm the name is `dineshuprety/laravel-zip-bomb-guard`, Packagist is enabled in your Composer configuration, and PHP/Laravel meet the [requirements](../README.md#requirements). |
| `ZipArchive` is missing | Enable `ext-zip` for the PHP binary running the command or worker. CLI PHP and web-server PHP can use different configurations. |
| Rule or Artisan command is missing | Install the package in the Laravel application and check that package discovery is not disabled for it. |
| Configuration changes appear ignored | Rebuild the application configuration cache and restart long-running workers; do not reuse an already-resolved checker after changing its settings. |
| A legitimate CSV/XML export fails the ratio check | Inspect a small representative sample and choose a deliberate ratio limit for that workload. Keep byte, entry, and depth limits in place. |
| `Unsafe entry path` for an unusual filename | The current check rejects any name containing `..`, even when it is not a parent-directory segment. Rename benign inputs when appropriate. |
| An archive with few outer files exceeds limits | Include nested containers, nested contents, and explicit directory entries in the count. |
| Retrying extraction reports a write failure | Use a new destination. Existing files are intentionally not overwritten. Check disk space and directory permissions as well. |
| A renamed ZIP is rejected | The checker inspects leading ZIP signatures, so changing `.zip` to `.bin` does not bypass recursive checks. |
| Temporary-file errors occur | Check the PHP temporary directory's permissions, free space, and worker resource limits. |

See [SUPPORT.md](../SUPPORT.md) for the information to include in a report. Suspected bypasses must follow [SECURITY.md](../SECURITY.md).

## Inspection boundaries

The checker recognizes ZIP-based archive extensions and standard leading ZIP signatures. Renamed self-extracting or prefixed ZIPs are not recognized by the prefix check, and TAR/RAR/7z formats are not supported by this engine. Ordinary files are not fully decompressed during inspection, and extension blocking is not a complete executable-content or malware detector.

Shared budgets bound the inspected tree; they are not a wall-clock deadline or a process-memory limit. Enforce upload, timeout, memory, and disk limits around the package. Use private local paths and keep the input and destination stable while processing. [The extraction guide](usage.md#extract-with-cleanup-on-failure) covers partial-output cleanup.
