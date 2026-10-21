<?php

namespace ZipBombGuard\Tests;

use ZipBombGuard\ArchiveSafetyChecker;
use ZipBombGuard\Concerns\SafeExtractsZips;
use ZipBombGuard\Exceptions\UnsafeArchiveException;
use ZipBombGuard\Rules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class ArchiveSafetyCheckerTest extends TestCase
{
    public function test_accepts_a_normal_archive(): void
    {
        $checker = new ArchiveSafetyChecker;

        $this->assertTrue($checker->isSafe($this->safeZip($this->tempPath('safe'))));
    }

    public function test_rejects_a_decompression_bomb(): void
    {
        $checker = new ArchiveSafetyChecker(maxRatio: 20);

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessageMatches('/compression ratio/i');

        $checker->assertSafe($this->bombZip($this->tempPath('bomb')));
    }

    public function test_rejects_zip_slip_paths(): void
    {
        $checker = new ArchiveSafetyChecker;

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessageMatches('/unsafe entry path/i');

        $checker->assertSafe($this->slipZip($this->tempPath('slip')));
    }

    public function test_rejects_archives_over_the_size_ceiling(): void
    {
        // 10 MB of zeros vs a 1 MB ceiling.
        $checker = new ArchiveSafetyChecker(maxTotalBytes: 1024 * 1024);

        $this->expectException(UnsafeArchiveException::class);

        $checker->assertSafe($this->bombZip($this->tempPath('big')));
    }

    public function test_rejects_empty_archives(): void
    {
        $path = $this->makeZip($this->tempPath('empty'), function () {
            // no entries
        });

        $this->expectException(UnsafeArchiveException::class);

        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_trait_never_writes_outside_the_destination(): void
    {
        $worker = new class {
            use SafeExtractsZips;
        };

        $dest = sys_get_temp_dir().'/zip-bomb-guard-extract-'.uniqid();

        $this->expectException(UnsafeArchiveException::class);

        try {
            $worker->extractSafely($this->slipZip($this->tempPath('slip2')), $dest);
        } finally {
            $this->assertFileDoesNotExist(dirname($dest).'/evil.txt');
        }
    }

    public function test_trait_extracts_safe_archives(): void
    {
        $worker = new class {
            use SafeExtractsZips;
        };

        $dest = sys_get_temp_dir().'/zip-bomb-guard-extract-'.uniqid();
        $worker->extractSafely($this->safeZip($this->tempPath('safe2')), $dest);

        $this->assertFileExists($dest.'/hello.txt');
        $this->assertStringContainsString('ZipBombGuard', file_get_contents($dest.'/hello.txt'));
    }

    public function test_config_is_published_and_used(): void
    {
        config(['zip-bomb-guard.max_ratio' => 2000]);

        $checker = app(ArchiveSafetyChecker::class);

        // With the relaxed ratio the mini-bomb (~1000:1) passes the scan.
        $this->assertTrue($checker->isSafe($this->bombZip($this->tempPath('bomb-relaxed'))));
    }

    public function test_rejects_duplicate_filenames(): void
    {
        $path = $this->makeZip($this->tempPath('dupes'), function (ZipArchive $zip) {
            $zip->addFromString('Report.txt', 'first');
            $zip->addFromString('REPORT.txt', 'second');
        });

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessageMatches('/duplicate entry/i');

        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_rejects_blocked_extensions(): void
    {
        $path = $this->makeZip($this->tempPath('blocked'), function (ZipArchive $zip) {
            $zip->addFromString('shell.php', '<?php echo "pwned";');
        });

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessageMatches('/blocked file type/i');

        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_rejects_nested_bomb_recursively(): void
    {
        $inner = $this->bombZip($this->tempPath('inner-bomb'));

        $path = $this->makeZip($this->tempPath('outer'), function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'inner.zip');
        });

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessageMatches('/compression ratio/i');

        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_accepts_nested_safe_archive(): void
    {
        $inner = $this->safeZip($this->tempPath('inner-safe'));

        $path = $this->makeZip($this->tempPath('outer-safe'), function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'inner.zip');
        });

        $this->assertTrue((new ArchiveSafetyChecker)->isSafe($path));
    }

    public function test_facade_resolves_to_the_checker(): void
    {
        $this->assertInstanceOf(
            ArchiveSafetyChecker::class,
            \ZipBombGuard\Facades\ZipBombGuard::getFacadeRoot()
        );
    }

    public function test_string_rule_accepts_safe_archive(): void
    {
        $file = new UploadedFile($this->safeZip($this->tempPath('rule-safe')), 'safe.zip', 'application/zip', null, true);

        $validator = Validator::make(['archive' => $file], ['archive' => 'no_zip_bomb']);

        $this->assertTrue($validator->passes());
    }

    public function test_string_rule_rejects_bomb(): void
    {
        $file = new UploadedFile($this->bombZip($this->tempPath('rule-bomb')), 'bomb.zip', 'application/zip', null, true);

        $validator = Validator::make(['archive' => $file], ['archive' => 'no_zip_bomb']);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('zip bomb', $validator->errors()->first('archive'));
    }

    public function test_rule_object_rejects_blocked_extension(): void
    {
        $file = new UploadedFile($this->makeZip($this->tempPath('rule-blocked'), function (ZipArchive $zip) {
            $zip->addFromString('shell.php', '<?php echo "pwned";');
        }), 'drop.zip', 'application/zip', null, true);

        $validator = Validator::make(
            ['archive' => $file],
            ['archive' => ['file', new Rules\NoZipBomb]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Blocked file type', $validator->errors()->first('archive'));
    }

    public function test_rule_object_accepts_safe_archive(): void
    {
        $file = new UploadedFile($this->safeZip($this->tempPath('rule-obj-safe')), 'safe.zip', 'application/zip', null, true);

        $validator = Validator::make(
            ['archive' => $file],
            ['archive' => ['file', new Rules\NoZipBomb]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_scan_command_flags_unsafe_archive(): void
    {
        $this->artisan('zip-bomb:scan', ['path' => $this->bombZip($this->tempPath('scan-bomb'))])
            ->expectsOutputToContain('UNSAFE')
            ->assertExitCode(0);
    }

    public function test_scan_command_fails_on_unsafe_with_flag(): void
    {
        $this->artisan('zip-bomb:scan', [
            'path' => $this->bombZip($this->tempPath('scan-bomb-flag')),
            '--fail-on-unsafe' => true,
        ])->assertExitCode(1);
    }

    public function test_scan_command_reports_safe_archive(): void
    {
        $this->artisan('zip-bomb:scan', ['path' => $this->safeZip($this->tempPath('scan-safe'))])
            ->expectsOutputToContain('SAFE')
            ->assertExitCode(0);
    }

    public function test_scan_command_rejects_missing_path(): void
    {
        $this->artisan('zip-bomb:scan', ['path' => '/does/not/exist'])
            ->assertExitCode(1);
    }
}
