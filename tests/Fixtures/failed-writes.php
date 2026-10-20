<?php

// Loaded only in isolated PHPUnit processes to simulate a full disk.
namespace DineshUprety\ZipBombGuard {
    function fwrite($stream, string $data): int
    {
        return 0;
    }
}

namespace DineshUprety\ZipBombGuard\Concerns {
    function fwrite($stream, string $data): int
    {
        return 0;
    }
}
