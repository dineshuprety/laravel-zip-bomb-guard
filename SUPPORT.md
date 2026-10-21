# Getting help

| Need | Start here |
| --- | --- |
| Installation and quick examples | [README](README.md) |
| Upload validation, extraction, and CLI behavior | [Usage guide](docs/usage.md) |
| Limits, environment variables, and rejected archives | [Configuration and troubleshooting](docs/configuration.md) |
| A reproducible non-security bug or documentation correction | [Open an issue](https://github.com/dineshuprety/laravel-zip-bomb-guard/issues/new/choose) |
| A feature proposal | [Feature request](https://github.com/dineshuprety/laravel-zip-bomb-guard/issues/new?template=feature_request.yml) |
| A suspected security bypass | [Report privately](SECURITY.md) |
| Contributing a fix | [Contributing guide](CONTRIBUTING.md) |

For usage questions, search [existing issues](https://github.com/dineshuprety/laravel-zip-bomb-guard/issues) first. If your question is not covered, open an issue describing what you are trying to do and the point where you are stuck. Help is provided as maintainer time allows.

## Useful information to include

Run these in the application where the problem occurs:

```bash
php -v
php --ri zip
composer show dineshuprety/laravel-zip-bomb-guard
composer show laravel/framework
```

Include the operating system, the relevant guard settings, the exact error message, and a small reproduction. Prefer a script that creates a harmless sample archive over attaching a real upload. Redact passwords, tokens, customer data, private paths, and unrelated configuration. Never post an entire `.env` file.

An unusual legitimate archive rejected by a size or compression limit can be a tuning question. A way to bypass the configured checks is a private security report.
