<?php

namespace ZipBombGuard;

use ZipBombGuard\Exceptions\UnsafeArchiveException;
use ZipArchive;

/**
 * Guards archive extraction against zip bombs (decompression bombs),
 * zip-slip path traversal, malicious symlinks, nested bombs, and other
 * hostile-archive tricks.
 *
 * Reads entry metadata from the zip central directory.
 * Nested archives are recursed into: each inner zip is streamed to a
 * temporary file under a byte cap and checked the same way.
 */
class ArchiveSafetyChecker
{
    /** @var string[] Lower-cased file extensions refused inside archives. */
    protected array $blockedExtensions;

    /** @var string[] Archive extensions that trigger a recursive check. */
    protected array $nestedArchiveExtensions = ['zip', 'jar', 'war', 'docx', 'xlsx', 'pptx'];

    public function __construct(
        protected int $maxTotalBytes = 100 * 1024 * 1024, // 100 MB uncompressed ceiling
        protected int $maxFiles = 1000,                    // entry count ceiling
        protected int $maxRatio = 20,                      // uncompressed:compressed ratio ceiling
        protected int $maxNestingDepth = 3,                // nested-archive recursion ceiling
        protected bool $rejectEncrypted = true,            // password-protected zips are a malware carrier
        protected bool $rejectSymlinks = true,             // symlinks escape the destination folder
        ?array $blockedExtensions = null,
    ) {
        $this->blockedExtensions = array_map(
            'strtolower',
            $blockedExtensions ?? ['php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'cmd', 'com', 'scr']
        );
    }

    /**
     * @throws UnsafeArchiveException when the archive is unsafe to extract.
     */
    public function assertSafe(string $path, int $depth = 0): void
    {
        $remainingBytes = $this->maxTotalBytes;
        $remainingFiles = $this->maxFiles;

        $this->scanArchive($path, $depth, $remainingBytes, $remainingFiles);
    }

    protected function scanArchive(string $path, int $depth, int &$remainingBytes, int &$remainingFiles): void
    {
        if ($depth < 0 || $depth > $this->maxNestingDepth) {
            throw new UnsafeArchiveException('Archive nesting depth exceeds the allowed limit.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new UnsafeArchiveException('File is not a readable zip archive.');
        }

        try {
            $count = $zip->numFiles;

            if ($count === 0) {
                throw new UnsafeArchiveException('Archive is empty.');
            }

            if ($count > $remainingFiles) {
                throw new UnsafeArchiveException('Archive tree has too many entries.');
            }

            $remainingFiles -= $count;
            $seen = [];

            for ($i = 0; $i < $count; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new UnsafeArchiveException('Could not read archive entry metadata.');
                }
                $name = (string) $stat['name'];

                // 1. Zip-slip / absolute path protection.
                if (
                    str_contains($name, '..')
                    || str_starts_with($name, '/')
                    || str_starts_with($name, '\\')
                    || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $name)
                ) {
                    throw new UnsafeArchiveException("Unsafe entry path in archive: {$name}");
                }

                // 2. Duplicate filenames: later entries silently overwrite earlier ones,
                //    a classic way to swap a benign file for a malicious one.
                $lower = strtolower($name);

                if (isset($seen[$lower])) {
                    throw new UnsafeArchiveException("Duplicate entry in archive: {$name}");
                }

                $seen[$lower] = true;

                // 3. Encrypted entries: password-protected zips are a favorite malware carrier.
                if ($this->rejectEncrypted && ($stat['encryption_method'] ?? 0) !== 0) {
                    throw new UnsafeArchiveException("Encrypted entry in archive: {$name}");
                }

                // 4. Symlinks: a symlink inside the archive can point outside the
                //    extraction folder, bypassing every path check.
                if ($this->rejectSymlinks && $this->isSymlink($zip, $i)) {
                    throw new UnsafeArchiveException("Symlink entry in archive: {$name}");
                }

                // 5. Blocked extensions: never let an archive drop executable scripts.
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($ext !== '' && in_array($ext, $this->blockedExtensions, true)) {
                    throw new UnsafeArchiveException("Blocked file type in archive: {$name}");
                }

                $size = (int) $stat['size'];

                // 6. Total uncompressed size ceiling — a bomb announces itself here.
                if ($size < 0 || $size > $remainingBytes) {
                    throw new UnsafeArchiveException('Archive uncompressed size exceeds the allowed limit.');
                }
                $remainingBytes -= $size;

                // 7. Decompression-bomb heuristic: a legitimate file rarely
                //    compresses better than ~20:1. A 42.zip-style bomb is ~10^6:1.
                $compressed = (int) $stat['comp_size'];
                if ($compressed > 0 && ($stat['size'] / $compressed) > $this->maxRatio) {
                    throw new UnsafeArchiveException("Suspicious compression ratio in entry: {$name}");
                }

                // 8. Recursive check: a nested archive gets the same treatment,
                //    streamed to temp under the byte cap before inspection.
                $nested = in_array($ext, $this->nestedArchiveExtensions, true);
                if (! $nested && $size >= 4 && ($stat['encryption_method'] ?? 0) === 0) {
                    $nested = $this->hasZipSignature($zip, $name);
                }

                if ($nested) {
                    if ($depth >= $this->maxNestingDepth) {
                        throw new UnsafeArchiveException('Archive nesting depth exceeds the allowed limit.');
                    }
                    $this->assertNestedSafe($zip, $name, $depth + 1, $size, $remainingBytes, $remainingFiles);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Convenience boolean wrapper around assertSafe().
     */
    public function isSafe(string $path): bool
    {
        try {
            $this->assertSafe($path);
        } catch (UnsafeArchiveException) {
            return false;
        }

        return true;
    }

    /**
     * Stream a nested archive entry to a temp file (under the byte cap) and
     * run the full safety check on it, recursively.
     *
     * @throws UnsafeArchiveException
     */
    protected function assertNestedSafe(
        ZipArchive $zip,
        string $entryName,
        int $depth,
        int $declaredSize,
        int &$remainingBytes,
        int &$remainingFiles,
    ): void {
        $stream = $zip->getStream($entryName);

        if ($stream === false) {
            throw new UnsafeArchiveException("Could not read nested archive: {$entryName}");
        }

        $temp = tempnam(sys_get_temp_dir(), 'zbg-nested-');

        if ($temp === false) {
            fclose($stream);
            throw new UnsafeArchiveException('Could not create temporary file for nested archive check.');
        }

        $handle = null;

        try {
            $handle = fopen($temp, 'wb');

            if ($handle === false) {
                throw new UnsafeArchiveException('Could not write temporary file for nested archive check.');
            }

            $written = 0;

            while (! feof($stream)) {
                $chunk = @fread($stream, 8192);

                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    throw new UnsafeArchiveException("Could not read nested archive: {$entryName}");
                }

                $written += strlen($chunk);

                if ($written > $declaredSize) {
                    throw new UnsafeArchiveException("Nested archive exceeds the safe size limit: {$entryName}");
                }

                if (@fwrite($handle, $chunk) !== strlen($chunk)) {
                    throw new UnsafeArchiveException('Could not write temporary file for nested archive check.');
                }
            }

            fclose($stream);
            fclose($handle);

            if ($written !== $declaredSize) {
                throw new UnsafeArchiveException("Nested archive size does not match its metadata: {$entryName}");
            }

            $this->scanArchive($temp, $depth, $remainingBytes, $remainingFiles);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($temp);
        }
    }

    protected function hasZipSignature(ZipArchive $zip, string $name): bool
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            throw new UnsafeArchiveException("Could not read archive entry: {$name}");
        }

        try {
            $signature = @fread($stream, 4);
            if ($signature === false || strlen($signature) !== 4) {
                throw new UnsafeArchiveException("Could not read archive entry: {$name}");
            }

            // shortcut: only leading signatures are checked; extend detection for prefixed/self-extracting ZIPs.
            return in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Detect Unix symlink entries from the zip's external attributes.
     */
    protected function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (! $zip->getExternalAttributesIndex($index, $system, $attributes)) {
            throw new UnsafeArchiveException('Could not read archive entry attributes.');
        }

        return $system === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & 0o170000) === 0o120000;
    }
}
