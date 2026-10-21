# Contributing

Small bug fixes, reproducible reports, clearer examples, and documentation improvements are welcome. Start with [support](SUPPORT.md) if you are unsure whether a behavior is a bug. Follow the [Code of Conduct](CODE_OF_CONDUCT.md) when participating.

You do not need to know the maintainer or have contributed before. Code lives under the shared project namespace `ZipBombGuard\`; contributors use the same namespace regardless of who writes a change.

**Report security bypasses privately through [SECURITY.md](SECURITY.md).** Do not disclose an unpatched bypass in a public issue or pull request.

## Set up a development checkout

You need Git, Composer 2, and PHP with `ext-zip`. Laravel 12 requires PHP 8.2 or later; Laravel 13 requires PHP 8.3 or later. Composer will report any additional missing extensions required by the test dependencies.

Fork the repository on GitHub, replace `YOUR_USERNAME` below, and choose a branch name that describes your change:

```bash
git clone https://github.com/YOUR_USERNAME/laravel-zip-bomb-guard.git
cd laravel-zip-bomb-guard
git remote add upstream https://github.com/dineshuprety/laravel-zip-bomb-guard.git
git switch -c improve-archive-checks
composer install
```

This is a package, not a Laravel application. Orchestra Testbench supplies the Laravel application used by the tests. Run the README's `php artisan` examples in a consuming Laravel application.

## Find the relevant code

Read [the architecture guide](docs/architecture.md) for component boundaries and cleanup responsibilities. The editable diagram source is [docs/architecture.svg](docs/architecture.svg).

| Area | Location |
| --- | --- |
| Archive inspection and recursive budgets | [src/ArchiveSafetyChecker.php](src/ArchiveSafetyChecker.php) |
| Guarded extraction | [src/Concerns/SafeExtractsZips.php](src/Concerns/SafeExtractsZips.php) |
| Upload rule object | [src/Rules/NoZipBomb.php](src/Rules/NoZipBomb.php) |
| Container registration, configuration, string rule | [src/ZipBombGuardServiceProvider.php](src/ZipBombGuardServiceProvider.php) |
| Command-line scanning | [src/Console/ScanArchivesCommand.php](src/Console/ScanArchivesCommand.php) |
| Integration tests | [tests/ArchiveSafetyCheckerTest.php](tests/ArchiveSafetyCheckerTest.php) |
| Security regression tests | [tests/SecurityRegressionTest.php](tests/SecurityRegressionTest.php) |

## Make a focused change

- Use the `ZipBombGuard\` namespace (`ZipBombGuard\Tests\` for tests), existing style, and supported PHP versions. Avoid adding dependencies for small helpers.
- For a bug fix, add a small regression test that fails before the fix and passes afterward.
- Preserve shared budgets, depth limits, path checks, resource cleanup, and failure handling when changing inspection or extraction.
- Generate small synthetic archives in tests. Do not commit real attack archives, personal uploads, credentials, or large compressed payloads.
- Create temporary files in a test-owned directory and remove them afterward. Use the existing isolated-process pattern for tests that override filesystem functions.
- Update affected examples and add a concise entry under `Unreleased` in [CHANGELOG.md](CHANGELOG.md).

Documentation-only changes do not need new behavior tests. Check commands, links, and code examples instead.

## Project skill for coding assistants

This repository includes [zip-bomb-guard-maintainer](.agents/skills/zip-bomb-guard-maintainer/SKILL.md), an instruction-only skill covering the architecture, security invariants, verification, and requested release work. Open the package checkout as your working folder so Codex can discover `.agents/skills`, following [the documented repository skill layout](https://developers.openai.com/codex/skills/).

Example request: `Use $zip-bomb-guard-maintainer to fix nested archive accounting and add a regression test.` Other assistants can read the same `SKILL.md` directly. The skill does not authorize publishing or grant account access.

## Verify your change

Run these from the package checkout:

```bash
composer validate --strict
vendor/bin/phpunit --fail-on-warning --fail-on-risky
composer audit
git diff --check
```

To run a focused test while working:

```bash
vendor/bin/phpunit --filter test_file_budget_is_shared_across_sibling_archives
```

The [CI workflow](.github/workflows/tests.yml) tests Laravel 12 and 13 across their supported PHP 8.2–8.5 combinations. A local run tests the versions installed in your checkout; it does not replace that matrix. `composer.lock` and `vendor/` are intentionally ignored for this library.

## Submit a pull request

Push your branch to your fork and target this repository's `main` branch. Explain the problem, the resulting behavior, and the checks you ran. Include a minimal reproduction for behavior changes and call out any compatibility impact.

If a feature needs a new public option, dependency, or different security policy, describe the use case in a feature request before investing in a large implementation. Small corrections can go straight to a pull request.

Contributions are distributed under the project's existing [MIT license](LICENSE).
