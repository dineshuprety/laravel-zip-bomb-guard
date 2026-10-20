<?php

namespace DineshUprety\ZipBombGuard\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void assertSafe(string $path, int $depth = 0)
 * @method static bool isSafe(string $path)
 *
 * @see \DineshUprety\ZipBombGuard\ArchiveSafetyChecker
 */
class ZipBombGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DineshUprety\ZipBombGuard\ArchiveSafetyChecker::class;
    }
}
