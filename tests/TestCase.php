<?php

namespace DineshUprety\ZipBombGuard\Tests;

use DineshUprety\ZipBombGuard\ZipBombGuardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ZipArchive;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ZipBombGuardServiceProvider::class];
    }

    protected function makeZip(string $path, callable $fill): string
    {
        $zip = new ZipArchive;

        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $fill($zip);
        $zip->close();

        return $path;
    }

    /** A harmless zip: one text file, modest compression. */
    protected function safeZip(string $path): string
    {
        return $this->makeZip($path, function (ZipArchive $zip) {
            $zip->addFromString('hello.txt', 'Hello from ZipBombGuard — nothing to see here.');
        });
    }

    /** A mini bomb: 10 MB of zeros compresses to ~10 KB (ratio ~1000:1). */
    protected function bombZip(string $path): string
    {
        return $this->makeZip($path, function (ZipArchive $zip) {
            $zip->addFromString('zeros.bin', str_repeat("\0", 10 * 1024 * 1024));
        });
    }

    /** Zip-slip: an entry that escapes the destination folder. */
    protected function slipZip(string $path): string
    {
        return $this->makeZip($path, function (ZipArchive $zip) {
            $zip->addFromString('../../evil.txt', 'you have been pwned');
        });
    }

    protected function tempPath(string $name): string
    {
        return sys_get_temp_dir().'/zip-bomb-guard-'.$name.'.zip';
    }
}
