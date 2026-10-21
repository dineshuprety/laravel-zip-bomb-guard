# Architecture

![Archive inspection and optional extraction flow](architecture.svg)

## Keep three boundaries

1. **Laravel adapters** accept application input and resolve the configured checker. The provider registers the singleton, facade, validation rules, and command. `NoZipBomb` and the string rule turn a failed scan into validation failure; the CLI reports it as `UNSAFE`.
2. **Archive inspection** lives in `ArchiveSafetyChecker`. `assertSafe()` creates fresh counters, reads metadata, recognizes nested ZIPs, and recursively checks nested contents using the same counters. It throws `UnsafeArchiveException` at the first rejection. `isSafe()` converts that exception to `false`.
3. **Guarded extraction** lives in `SafeExtractsZips`. It first calls the checker, then reopens the archive and streams outer entries into a private destination with path checks, exclusive file creation, and a cap on actual bytes written.

Keep this layout as the package grows. Add a new entry point as a thin adapter to the checker. Put new archive policy in the checker and filesystem write protection in the extraction trait. There is no need for another service layer or a separate engine for each adapter.

## Shared budgets

The service provider registers the checker as a singleton, so budget counters must remain local to each top-level call. Recursive scans receive those counters by reference. Sibling archives cannot each spend the full limit.

For an outer ZIP containing two inner ZIPs with ten files each:

- The entry budget spends **22 entries**: two containers plus twenty contents. Directory entries also count when present.
- The byte budget spends the declared uncompressed sizes of both ZIP containers and their contents.
- Each nested stream is also bounded by its declared size and must produce exactly that many bytes before recursion.
- Exceeding the nesting depth rejects the archive, even if the deeper contents were never inspected.

## Failure and cleanup

| Stage | Package behavior | Application responsibility |
| --- | --- | --- |
| Inspection rejects an archive | Throw `UnsafeArchiveException`; close archive/stream handles and remove nested temporary files | Reject the upload or stop the job; manage the original upload's lifecycle |
| Validation rule rejects input | Add a validation failure | Return an appropriate validation response |
| Extraction fails during a write | Close handles and remove the current partial file | Discard the whole destination; earlier completed files and directories may remain |
| CLI finds an unsafe archive | Print `UNSAFE`; return a failure exit code when `--fail-on-unsafe` is supplied | Use that flag when the command gates CI or another automated step |

The extraction byte limit comes from Laravel configuration, including when a custom inspection checker is supplied. Extraction does not recursively unpack nested ZIP files.

## Trust boundaries and limits

Archive metadata and filenames are untrusted. A passing metadata scan is not a malware verdict or a full decompression/integrity check of ordinary files. Known ZIP extensions and leading ZIP signatures trigger nesting checks; renamed prefixed/self-extracting ZIPs are outside the signature heuristic. Other archive formats are outside this package's scope.

Inspection and extraction are separate operations. Keep the source archive unchanged and use a fresh destination outside the public web root that only the worker can modify. Enforce upload, worker time, memory, and disk limits separately. See [configuration and limitations](configuration.md) and [usage and cleanup](usage.md).

## Change the diagram

Edit [architecture.svg](architecture.svg) directly. It uses text and vector shapes so contributors can review the source and render it without an image-generation service. Update this page and the README when a boundary or guarantee changes. The diagram describes the current implementation, not a proposed roadmap.
