# Security Policy

## Reporting a vulnerability

This package exists to stop archive-based attacks, so its own security matters more than most. If you find a vulnerability, **do not open a public issue**.

Use [GitHub private vulnerability reporting](https://github.com/dineshuprety/laravel-zip-bomb-guard/security/advisories/new), or email **codewithdinesh@gmail.com**, with:

- a description of the vulnerability,
- steps to reproduce (a proof-of-concept archive is ideal),
- the package version you tested against.

You can expect an acknowledgement within 48 hours and a patched release as soon as a fix is verified.

## Scope

In scope: anything that lets a hostile archive pass `ArchiveSafetyChecker::assertSafe()`, bypass the `NoZipBomb` / `no_zip_bomb` validation, or escape the destination folder during `SafeExtractsZips::extractSafely()`.

Out of scope: the compression-ratio heuristic is intentionally a heuristic — reports that a *legitimate, unusual* archive trips it are tuning feedback, not vulnerabilities. Send those as regular issues.

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |
