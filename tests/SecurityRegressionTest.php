<?php

namespace DineshUprety\ZipBombGuard\Tests;

use DineshUprety\ZipBombGuard\ArchiveSafetyChecker;
use DineshUprety\ZipBombGuard\Concerns\SafeExtractsZips;
use DineshUprety\ZipBombGuard\Exceptions\UnsafeArchiveException;
use DineshUprety\ZipBombGuard\Rules\NoZipBomb;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ZipArchive;

class SecurityRegressionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/zbg-regression-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_rejects_unix_symlinks(): void
    {
        $path = $this->makeZip($this->directory.'/link.zip', function (ZipArchive $zip) {
            $zip->addFromString('link.txt', '/etc/passwd');
            $zip->setExternalAttributesName('link.txt', ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        });

        $this->assertTrue((new ArchiveSafetyChecker(rejectSymlinks: false))->isSafe($path));
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('Symlink entry');
        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_rejects_nested_archives_beyond_the_depth_limit(): void
    {
        $inner = $this->safeZip($this->directory.'/inner.zip');
        $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'inner.zip');
        });

        $this->assertTrue((new ArchiveSafetyChecker(maxNestingDepth: 1))->isSafe($outer));
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('nesting depth');
        (new ArchiveSafetyChecker(maxNestingDepth: 0))->assertSafe($outer);
    }

    public function test_rejects_archives_hidden_one_level_beyond_the_limit(): void
    {
        $path = $this->safeZip($this->directory.'/payload.zip');
        for ($i = 0; $i < 2; $i++) {
            $inner = $path;
            $path = $this->makeZip($this->directory."/nested-{$i}.zip", function (ZipArchive $zip) use ($inner) {
                $zip->addFile($inner, 'inner.zip');
            });
        }

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('nesting depth');
        (new ArchiveSafetyChecker(maxNestingDepth: 1))->assertSafe($path);
    }

    public function test_extraction_rejects_existing_symlink_files(): void
    {
        mkdir($this->directory.'/output');
        file_put_contents($this->directory.'/outside.txt', 'unchanged');
        symlink($this->directory.'/outside.txt', $this->directory.'/output/hello.txt');

        $worker = new class { use SafeExtractsZips; };
        $this->expectException(UnsafeArchiveException::class);

        try {
            $worker->extractSafely($this->safeZip($this->directory.'/safe.zip'), $this->directory.'/output');
        } finally {
            $this->assertSame('unchanged', file_get_contents($this->directory.'/outside.txt'));
        }
    }

    public function test_extraction_rejects_symlink_parents_before_creating_directories(): void
    {
        mkdir($this->directory.'/output');
        mkdir($this->directory.'/outside');
        symlink($this->directory.'/outside', $this->directory.'/output/link');
        $path = $this->makeZip($this->directory.'/safe.zip', function (ZipArchive $zip) {
            $zip->addFromString('link/new/hello.txt', 'hello');
        });

        $worker = new class { use SafeExtractsZips; };
        $this->expectException(UnsafeArchiveException::class);

        try {
            $worker->extractSafely($path, $this->directory.'/output');
        } finally {
            $this->assertDirectoryDoesNotExist($this->directory.'/outside/new');
        }
    }

    public function test_extraction_does_not_overwrite_existing_files(): void
    {
        mkdir($this->directory.'/output');
        file_put_contents($this->directory.'/output/hello.txt', 'unchanged');
        $worker = new class { use SafeExtractsZips; };
        $this->expectException(UnsafeArchiveException::class);

        try {
            $worker->extractSafely($this->safeZip($this->directory.'/safe.zip'), $this->directory.'/output');
        } finally {
            $this->assertSame('unchanged', file_get_contents($this->directory.'/output/hello.txt'));
        }
    }

    public function test_extraction_uses_the_configured_checker(): void
    {
        config(['zip-bomb-guard.max_files' => 1]);
        $path = $this->makeZip($this->directory.'/safe.zip', function (ZipArchive $zip) {
            $zip->addFromString('one.txt', 'one');
            $zip->addFromString('two.txt', 'two');
        });
        $worker = new class { use SafeExtractsZips; };

        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('too many entries');
        $worker->extractSafely($path, $this->directory.'/output');
    }

    public function test_extraction_creates_safe_subdirectories(): void
    {
        $path = $this->makeZip($this->directory.'/safe.zip', function (ZipArchive $zip) {
            $zip->addFromString('reports/daily/hello.txt', 'hello');
        });
        $worker = new class { use SafeExtractsZips; };
        $worker->extractSafely($path, $this->directory.'/output');

        $this->assertSame('hello', file_get_contents($this->directory.'/output/reports/daily/hello.txt'));
    }

    public function test_extraction_removes_a_partial_file_when_the_byte_limit_is_hit(): void
    {
        config(['zip-bomb-guard.max_total_bytes' => 9000]);
        $path = $this->makeZip($this->directory.'/safe.zip', function (ZipArchive $zip) {
            $zip->addFromString('data.bin', random_bytes(16384));
        });
        $worker = new class { use SafeExtractsZips; };
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('Extraction exceeded');

        try {
            $worker->extractSafely($path, $this->directory.'/output', new ArchiveSafetyChecker);
        } finally {
            $this->assertFileDoesNotExist($this->directory.'/output/data.bin');
        }
    }

    public function test_rule_rejects_non_file_values_without_crashing(): void
    {
        foreach ([123, true, ['file'], new \stdClass] as $value) {
            $validator = Validator::make(['archive' => $value], ['archive' => [new NoZipBomb]]);
            $this->assertTrue($validator->fails());
        }
    }

    public function test_file_budget_is_shared_across_sibling_archives(): void
    {
        $inner = $this->safeZip($this->directory.'/inner.zip');
        $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'one.zip');
            $zip->addFile($inner, 'two.zip');
        });

        $this->assertTrue((new ArchiveSafetyChecker(maxFiles: 4))->isSafe($outer));
        $checker = new ArchiveSafetyChecker(maxFiles: 3);
        $this->assertFalse($checker->isSafe($outer));
        $this->assertTrue($checker->isSafe($inner), 'A failed scan must not consume the next scan budget.');
    }

    public function test_byte_budget_is_shared_across_sibling_archives(): void
    {
        $payload = random_bytes(256);
        $inner = $this->makeZip($this->directory.'/inner.zip', function (ZipArchive $zip) use ($payload) {
            $zip->addFromString('data.bin', $payload);
        });
        $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'one.zip');
            $zip->addFile($inner, 'two.zip');
        });
        $total = 2 * (filesize($inner) + strlen($payload));

        $checker = new ArchiveSafetyChecker(maxTotalBytes: $total);
        $this->assertTrue($checker->isSafe($outer));
        $this->assertTrue($checker->isSafe($outer), 'Each top-level scan gets a fresh budget.');
        $this->assertFalse((new ArchiveSafetyChecker(maxTotalBytes: $total - 1))->isSafe($outer));
    }

    public function test_rejects_blocked_files_in_renamed_nested_archives(): void
    {
        $inner = $this->makeZip($this->directory.'/inner.zip', function (ZipArchive $zip) {
            $zip->addFromString('shell.php', '<?php echo "blocked";');
        });

        foreach (['payload.bin', 'payload'] as $name) {
            $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner, $name) {
                $zip->addFile($inner, $name);
            });
            $this->assertFalse((new ArchiveSafetyChecker)->isSafe($outer), $name);
        }
    }

    public function test_renamed_nested_archives_obey_depth_and_file_limits(): void
    {
        $inner = $this->safeZip($this->directory.'/inner.zip');
        $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'payload.bin');
        });

        $this->assertTrue((new ArchiveSafetyChecker)->isSafe($outer));
        $this->assertFalse((new ArchiveSafetyChecker(maxNestingDepth: 0))->isSafe($outer));
        $this->assertFalse((new ArchiveSafetyChecker(maxFiles: 1))->isSafe($outer));
    }

    public function test_rejects_encrypted_entries_by_default(): void
    {
        $path = $this->makeZip($this->directory.'/encrypted.zip', function (ZipArchive $zip) {
            $zip->addFromString('secret.txt', 'test fixture');
            $this->assertTrue($zip->setEncryptionName('secret.txt', ZipArchive::EM_AES_256, 'fixture-only'));
        });

        $this->assertTrue((new ArchiveSafetyChecker(rejectEncrypted: false))->isSafe($path));
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('Encrypted entry');
        (new ArchiveSafetyChecker)->assertSafe($path);
    }

    public function test_rejects_truncated_archives(): void
    {
        $path = $this->safeZip($this->directory.'/truncated.zip');
        file_put_contents($path, substr(file_get_contents($path), 0, -12));

        $this->assertFalse((new ArchiveSafetyChecker)->isSafe($path));
    }

    public function test_rejects_corrupt_nested_archives_even_when_renamed(): void
    {
        foreach (['broken.zip', 'broken.bin'] as $name) {
            $path = $this->makeZip($this->directory.'/corrupt.zip', function (ZipArchive $zip) use ($name) {
                $zip->addFromString($name, "PK\x03\x04invalid zip data");
            });
            $this->assertFalse((new ArchiveSafetyChecker)->isSafe($path), $name);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_failed_extraction_write_removes_partial_output(): void
    {
        require __DIR__.'/Fixtures/failed-writes.php';
        $worker = new class { use SafeExtractsZips; };
        $path = $this->safeZip($this->directory.'/safe.zip');
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('Could not write extracted entry');

        try {
            $worker->extractSafely($path, $this->directory.'/output');
        } finally {
            $this->assertFileDoesNotExist($this->directory.'/output/hello.txt');
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_failed_nested_write_removes_temporary_file(): void
    {
        require __DIR__.'/Fixtures/failed-writes.php';
        $inner = $this->safeZip($this->directory.'/inner.zip');
        $outer = $this->makeZip($this->directory.'/outer.zip', function (ZipArchive $zip) use ($inner) {
            $zip->addFile($inner, 'inner.zip');
        });
        $before = glob(sys_get_temp_dir().'/zbg-nested-*');
        $this->expectException(UnsafeArchiveException::class);
        $this->expectExceptionMessage('Could not write temporary file');

        try {
            (new ArchiveSafetyChecker)->assertSafe($outer);
        } finally {
            $this->assertSame($before, glob(sys_get_temp_dir().'/zbg-nested-*'));
        }
    }
}
