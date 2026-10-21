# Changelog

## [Unreleased]

## [2.0.0] - 2026-10-21

### Changed

- **Breaking:** move all public PHP classes and the extraction trait from `DineshUprety\ZipBombGuard\` to the project namespace `ZipBombGuard\`. Old fully qualified names are not aliased.
- Update Composer autoloading, Laravel discovery metadata, tests, fixtures, and usage examples for the new namespace.
- Make the shared project namespace explicit in contributor and coding-assistant guidance.

### Added

- A [1.x to 2.x upgrade guide](docs/upgrading.md) covering imports, explicit provider registration, container bindings, and Laravel caches.

### Compatibility

- The Composer package name remains `dineshuprety/laravel-zip-bomb-guard`. PHP/Laravel requirements, configuration keys, validation rule name, Artisan command, and archive safety behavior are unchanged.
- Existing 1.x tags remain available. Upgrade applications' class references before selecting `^2.0`.

## [1.0.1] - 2026-10-21

### Fixed

- Allow Symfony Finder 8 so the package installs into Laravel 13 applications that already use it, without downgrading that dependency.

### Added

- Explicit Symfony Finder 8 CI coverage and a recursive directory-scanning test.
- Contributor, support, and conduct guidelines, plus public issue forms and a pull request template.
- Guides for application usage, extraction cleanup, configuration, troubleshooting, and release maintenance.
- Documentation navigation and a live test-status badge in the README.
- An editable architecture diagram and guide covering shared budgets, component boundaries, and failure cleanup.
- A repository-scoped maintainer skill for coding assistants.
- Packagist publication and standard Composer installation instructions.

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

[2.0.0]: https://github.com/dineshuprety/laravel-zip-bomb-guard/releases/tag/v2.0.0
[1.0.1]: https://github.com/dineshuprety/laravel-zip-bomb-guard/releases/tag/v1.0.1
[1.0.0]: https://github.com/dineshuprety/laravel-zip-bomb-guard/releases/tag/v1.0.0
