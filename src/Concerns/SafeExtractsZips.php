<?php

namespace DineshUprety\ZipBombGuard\Concerns;

use DineshUprety\ZipBombGuard\ArchiveSafetyChecker;
use DineshUprety\ZipBombGuard\Exceptions\UnsafeArchiveException;
use ZipArchive;

/**
 * Safe extraction for queued jobs and controllers.
 *
 * The byte counter runs DURING decompression, so declared sizes in the
 * central directory cannot lie to you while data is being written.
 *
 * Usage:
 *   class ProcessExportArchive implements ShouldQueue
 *   {
 *       use SafeExtractsZips;
 *
 *       public function handle(ArchiveSafetyChecker $checker): void
 *       {
 *           $this->extractSafely($this->archivePath, $this->destination);
 *       }
 *   }
 */
trait SafeExtractsZips
{
    /**
     * @throws UnsafeArchiveException
     */
    public function extractSafely(string $archivePath, string $destination, ?ArchiveSafetyChecker $checker = null): void
    {
        $checker ??= app(ArchiveSafetyChecker::class);

        // Layer 1: inspect the central directory before touching anything.
        $checker->assertSafe($archivePath);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new UnsafeArchiveException('File is not a readable zip archive.');
        }

        try {
            if (! is_dir($destination) && ! mkdir($destination, 0755, true)) {
                throw new UnsafeArchiveException('Could not create extraction directory.');
            }

            $root = realpath($destination);
            if ($root === false) {
                throw new UnsafeArchiveException('Could not resolve extraction directory.');
            }
            $root = rtrim($root, DIRECTORY_SEPARATOR);
            $maxTotalBytes = (int) config('zip-bomb-guard.max_total_bytes', 100 * 1024 * 1024);
            $written = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (str_ends_with($name, '/')) {
                    continue; // directory entry
                }

                $target = $root.DIRECTORY_SEPARATOR.$name;
                $resolved = $this->normalizePath($target);

                // Layer 2: resolved-path zip-slip guard (catches tricks like a/../b).
                if (! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                    throw new UnsafeArchiveException("Unsafe entry path in archive: {$name}");
                }

                // Check each parent before mkdir can follow an existing symlink.
                $dir = $root;
                $relativeDir = substr(dirname($resolved), strlen($root));
                foreach (explode(DIRECTORY_SEPARATOR, $relativeDir) as $segment) {
                    if ($segment === '') {
                        continue;
                    }
                    $dir .= DIRECTORY_SEPARATOR.$segment;
                    if (is_link($dir)) {
                        throw new UnsafeArchiveException("Symlink in extraction path: {$name}");
                    }
                    if (! is_dir($dir) && ! mkdir($dir, 0755)) {
                        throw new UnsafeArchiveException("Could not create extraction directory: {$name}");
                    }
                }

                $stream = $zip->getStream($name);

                if ($stream === false) {
                    throw new UnsafeArchiveException("Could not read entry from archive: {$name}");
                }

                // Exclusive creation prevents overwriting files, hardlinks, or symlinks.
                $handle = @fopen($resolved, 'xb');

                if ($handle === false) {
                    fclose($stream);
                    throw new UnsafeArchiveException("Could not write extracted entry: {$name}");
                }

                // Layer 3: byte-counted streaming extraction. Stop the moment
                // the ceiling is hit, no matter what the headers claimed.
                try {
                    while (! feof($stream)) {
                        $chunk = @fread($stream, 8192);

                        if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                            throw new UnsafeArchiveException("Could not read entry from archive: {$name}");
                        }

                        $written += strlen($chunk);

                        if ($written > $maxTotalBytes) {
                            throw new UnsafeArchiveException('Extraction exceeded the safe size limit.');
                        }

                        if (@fwrite($handle, $chunk) !== strlen($chunk)) {
                            throw new UnsafeArchiveException("Could not write extracted entry: {$name}");
                        }
                    }
                } catch (\Throwable $e) {
                    fclose($handle);
                    @unlink($resolved);
                    throw $e;
                } finally {
                    fclose($stream);
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    protected function normalizePath(string $path): string
    {
        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        return (str_starts_with($path, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '').implode(DIRECTORY_SEPARATOR, $parts);
    }
}
