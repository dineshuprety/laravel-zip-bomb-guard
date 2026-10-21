<?php

namespace ZipBombGuard\Console;

use ZipBombGuard\ArchiveSafetyChecker;
use ZipBombGuard\Exceptions\UnsafeArchiveException;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Scan one archive — or every archive in a directory — for zip bombs
 * and other hostile-archive tricks.
 *
 *   php artisan zip-bomb:scan storage/app/uploads
 *   php artisan zip-bomb:scan storage/app/backups --fail-on-unsafe
 */
class ScanArchivesCommand extends Command
{
    protected $signature = 'zip-bomb:scan
        {path : Path to an archive file or a directory to scan recursively}
        {--fail-on-unsafe : Exit with a non-zero code when any archive is unsafe}';

    protected $description = 'Scan archives for zip bombs and other hostile-archive tricks';

    public function handle(ArchiveSafetyChecker $checker): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("Path does not exist: {$path}");

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? (new Finder)->files()->in($path)->name('*.zip')->name('*.jar')->name('*.war')->name('*.docx')->name('*.xlsx')->name('*.pptx')
            : [$path];

        $checked = 0;
        $unsafe = 0;

        foreach ($files as $file) {
            $filePath = is_string($file) ? $file : $file->getRealPath();
            $checked++;

            try {
                $checker->assertSafe($filePath);
                $this->info("SAFE   {$filePath}");
            } catch (UnsafeArchiveException $e) {
                $unsafe++;
                $this->error("UNSAFE {$filePath}");
                $this->line("       {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->line("Checked {$checked} archive(s): {$unsafe} unsafe.");

        if ($unsafe > 0 && $this->option('fail-on-unsafe')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
