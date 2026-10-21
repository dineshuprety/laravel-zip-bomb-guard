<?php

// Loaded only in isolated PHPUnit processes to simulate a full disk.
namespace ZipBombGuard {
    function fwrite($stream, string $data): int
    {
        return 0;
    }
}

namespace ZipBombGuard\Concerns {
    function fwrite($stream, string $data): int
    {
        return 0;
    }
}
