# Changelog

## [1.0.0] - 2026-10-20

Initial public tagged release.

### Added

- `ArchiveSafetyChecker` for ZIP paths, duplicate names, encryption, symlinks, blocked extensions, size, compression ratios, and nested archives.
- Shared byte and entry budgets across the entire nested archive tree, with a fresh budget for each top-level scan.
- Nested ZIP detection by known extensions and leading ZIP signatures, including renamed files.
- `ZipBombGuard` facade, `NoZipBomb` rule object, and `no_zip_bomb` string validation rule.
- `SafeExtractsZips` trait with counted streaming, overwrite protection, and destination symlink checks.
- `zip-bomb:scan` Artisan command, including `--fail-on-unsafe`.
- Publishable Laravel configuration with environment overrides.
- Tests for encrypted/truncated/corrupt archives, recursive limits, renamed ZIPs, failed writes, resource cleanup, and Laravel integrations.
- Laravel 12/13 CI on supported PHP 8.2–8.5 combinations, dependency auditing, and weekly Dependabot checks.
- Private vulnerability reporting and a security policy.

### Fixed before release

- Read Unix symlink attributes through the ZIP API.
- Reject nesting beyond the depth limit.
- Reject malformed validation values without PHP errors.
- Apply the configured Laravel checker consistently.
- Reject failed reads/writes and remove partial extraction files.

### Compatibility

- The initial development snapshot used per-archive byte and file limits. The release shares these limits across the full nested tree, including container entries, and may reject archives that previously passed.
- Extraction rejects existing output files. Use a fresh private destination and discard it after a failed extraction.

[1.0.0]: https://github.com/dineshuprety/laravel-zip-bomb-guard/releases/tag/v1.0.0
