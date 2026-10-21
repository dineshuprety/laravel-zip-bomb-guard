# Maintainer guide

Releases are cut from reviewed commits that passed the full CI matrix. Documentation changes can remain under `Unreleased` until the next package release.

## Triage and review

- Route suspected security bypasses to the private process in [SECURITY.md](../SECURITY.md).
- Ask for a minimal reproduction, relevant versions, and only the guard settings needed to understand a public bug report.
- Check changes to limits, path handling, nesting, and extraction against [the security regression tests](../tests/SecurityRegressionTest.php).
- Review Dependabot PRs and their compatibility impact. Update checks do not enable automatic merging.
- Keep fixes focused and document changes to accepted/rejected archives, configuration, or public APIs.

## Prepare a release

1. Start from an up-to-date, clean `main` checkout. Choose the next unused semantic version; do not reuse an existing tag.
2. Move the applicable `Unreleased` entries in [CHANGELOG.md](../CHANGELOG.md) into the new version with its release date. Check the README requirements, installation examples, and [supported security versions](../SECURITY.md#supported-versions).
3. Run the contributor checks below, then commit and push the release preparation.

```bash
composer validate --strict
vendor/bin/phpunit --fail-on-warning --fail-on-risky
composer audit
git diff --check
```

4. Wait for the [Tests workflow](https://github.com/dineshuprety/laravel-zip-bomb-guard/actions/workflows/tests.yml) to pass for that exact commit across every supported PHP/Laravel combination. Investigate failures rather than ignoring them to make a release green.
5. Write release notes covering the problem solved, resulting behavior, compatibility changes, installation, and material limitations. For a coordinated security fix, follow the private advisory process before disclosure.

## Tag the tested commit

The following is an example for a future `v2.0.1` release, not a tag to run unchanged. Set the next unused version and the full commit SHA that passed CI. Save the reviewed release notes to a local file first.

```bash
release_tag=v2.0.1
release_commit=FULL_TESTED_COMMIT_SHA
git tag -a "$release_tag" "$release_commit" -m "$release_tag"
git push origin "$release_tag"
gh release create "$release_tag" \
  --repo dineshuprety/laravel-zip-bomb-guard \
  --verify-tag \
  --title "Laravel Zip Bomb Guard $release_tag" \
  --notes-file /path/to/reviewed-release-notes.md
```

Verify the published release and tag point to the tested commit. Treat published tags as immutable: issue a new version for a correction rather than moving a tag that consumers may have installed. Keep the Composer version derived from Git tags; do not add a hard-coded `version` field to `composer.json`.

## Packagist updates

The package is registered as [dineshuprety/laravel-zip-bomb-guard](https://packagist.org/packages/dineshuprety/laravel-zip-bomb-guard). Version `v1.0.0` was published on 2026-10-21.

A GitHub webhook automatically updates Packagist on pushes, including new release tags. It uses JSON payloads, HTTPS verification, and the Packagist safe API token as its signing secret, following [Packagist's documented GitHub integration](https://packagist.org/about#how-to-update-packages). Keep integration tokens out of the repository, issue reports, and release notes.

After pushing, check that the expected version and commit appear on Packagist. If an update is missing, inspect recent deliveries under the repository's **Settings → Webhooks** and retry a failed delivery after fixing the cause. The package page's update action remains a manual fallback. If the Packagist API tokens are rotated, update the webhook's secret too.

Consumers install through the default Composer registry:

```bash
composer require dineshuprety/laravel-zip-bomb-guard
```

Verify installation in a fresh supported Laravel application. Do not announce a new version until it is visible on Packagist and resolves through Composer. Existing installations with a custom VCS repository can remove that override as described in the [installation guide](../README.md#install).
