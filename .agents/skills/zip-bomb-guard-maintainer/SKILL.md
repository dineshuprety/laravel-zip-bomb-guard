---
name: zip-bomb-guard-maintainer
description: Maintain dineshuprety/laravel-zip-bomb-guard. Use for changes to this package's archive inspection, extraction, Laravel adapters, tests, documentation, or requested releases. Do not use for unrelated Laravel apps or generic ZIP handling.
---

# Zip Bomb Guard maintainer

Work from this package's repository root, three directories above this skill. Confirm `composer.json` names `dineshuprety/laravel-zip-bomb-guard` before running commands. This is a library; run Artisan examples in a consuming Laravel application, not this checkout.

Version 2 uses the project namespace `ZipBombGuard\`, with tests under `ZipBombGuard\Tests\`. Preserve it for all contributors. The Composer vendor name is separate from PHP namespaces; see [the 1.x migration guide](../../../docs/upgrading.md) for legacy imports.

## Choose the affected boundary

Read [the architecture](../../../docs/architecture.md) and only the relevant source and tests. Search callers before changing a public method or configuration key.

| Change | Source | Verification |
| --- | --- | --- |
| Inspection, nesting, budget accounting | `src/ArchiveSafetyChecker.php` | `tests/SecurityRegressionTest.php`, `tests/ArchiveSafetyCheckerTest.php` |
| Extraction, destination paths, write failures | `src/Concerns/SafeExtractsZips.php` | Extraction and filesystem-failure tests in those same test files |
| Container/configuration and validation | `src/ZipBombGuardServiceProvider.php`, `src/Rules/NoZipBomb.php`, `src/Facades/ZipBombGuard.php`, `config/zip-bomb-guard.php` | Existing Testbench integration tests |
| CLI selection/output/exit codes | `src/Console/ScanArchivesCommand.php` | Existing command tests |

Keep security policy in the checker and guarded writes in the extraction trait. Adapters should use the configured container checker rather than reimplement checks. Preserve supported PHP/Laravel constraints from `composer.json` and the CI matrix; do not introduce an application scaffold or a new dependency for a small helper.

## Preserve the security contract

- Start fresh byte and entry counters for each public scan; share those counters across sibling and nested archives. Never store mutable budgets on the singleton checker.
- Count nested container entries and directory entries as well as their contents. Reject excessive nesting; never skip a deeper archive and report success.
- Preserve path, duplicate-name, encryption, symlink, extension, size, and ratio checks. Recognize known nested extensions and leading ZIP signatures; retain the documented limitation for renamed prefixed/self-extracting ZIPs.
- Bound nested temporary streams by the declared size, reject read/write failures, and close handles and remove temporary files on every exit path.
- During extraction, check destination parents for symlinks before creating directories, use exclusive output creation, and count actual streamed bytes against Laravel's configured limit. A custom inspection checker does not replace that extraction limit.
- On extraction failure, remove the current partial file. Earlier completed files can remain: examples must tell callers to discard the destination. Use a fresh private directory and an unchanged source archive; do not claim atomic protection from concurrent filesystem changes.
- Do not describe a passing scan as malware detection or full decompression/integrity verification of ordinary entries. Extraction unpacks only the outer archive.

## Implement and check

For a behavior fix, add a small regression that fails before the fix and passes afterward. Reuse existing synthetic ZIP helpers and isolated-process filesystem fault tests. Never add real bombs, personal uploads, secrets, or large payloads as fixtures.

Follow [CONTRIBUTING.md](../../../CONTRIBUTING.md). Run a focused test while editing, then these checks for runtime or dependency changes:

```bash
composer validate --strict
vendor/bin/phpunit --fail-on-warning --fail-on-risky
composer audit
git diff --check
```

For documentation-only work, check affected commands, relative links, and diagram rendering instead of adding behavior tests. Keep `README.md`, relevant `docs/` pages, and `CHANGELOG.md` aligned with changed behavior. Edit `docs/architecture.svg` as the diagram source; do not introduce a second conflicting diagram.

## Release only within the user's request

Use [docs/maintaining.md](../../../docs/maintaining.md) when a release or publication is requested. This skill is guidance, not permission to push, tag, publish, send messages, or change account access. Honor authorization already supplied in the conversation without asking again unnecessarily.

Before a requested release, verify the full CI matrix on the exact commit. Use a new immutable semantic-version tag and derive the Composer version from Git. Confirm the expected version is visible on Packagist before claiming availability. Keep credentials out of files and outputs. Route suspected bypasses through [SECURITY.md](../../../SECURITY.md).

Report the changed behavior, checks actually run, and remaining limits. Do not call a review an independent security audit.
