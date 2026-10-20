# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed

- Read ZIP external attributes through `ZipArchive` so Unix symlink entries are rejected.
- Reject nested archives beyond the configured depth instead of skipping inspection.
- Reject existing output files and symlink parent directories during extraction.
- Use the configured Laravel checker for extraction and validation.
- Reject non-file validation values without throwing a PHP error.
- Report streaming read/write failures, close resources, and remove partial output files.

### Added

- Regression tests for security checks, extraction, configuration, and validation.
- PHP 8.5 in CI, read-only workflow permissions, and Git ignore rules.
- GitHub installation instructions and documented inspection/extraction limits.

## [1.0.0] - 2026-10-17

### Added

- `ArchiveSafetyChecker` — 8-check engine: zip-slip paths, duplicate filenames, encrypted entries, symlinks, blocked extensions, total uncompressed size ceiling, compression-ratio heuristic, recursive nested-archive scan with byte-capped temp streaming.
- `ZipBombGuard` facade with `assertSafe()` / `isSafe()` (auto-discovered alias).
- `NoZipBomb` validation rule object and `no_zip_bomb` string rule for upload validation.
- `SafeExtractsZips` trait for queue workers: central-directory scan, resolved-path zip-slip guard, byte-counted streaming extraction.
- `zip-bomb:scan` Artisan command for auditing archives on the server (`--fail-on-unsafe` for CI).
- Publishable config `zip-bomb-guard.php` with environment variable overrides.
- GitHub Actions CI matrix: PHP 8.2–8.4 × Laravel 12–13.
- `SECURITY.md` vulnerability reporting policy.
- 17 tests covering bombs, zip-slip, nested bombs, blocked extensions, duplicates, validation rules, facade, and config.
