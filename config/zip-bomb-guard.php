<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum total uncompressed size (bytes)
    |--------------------------------------------------------------------------
    |
    | The sum of every entry's declared uncompressed size may not exceed this.
    | A zip bomb announces itself here: 42 KB claiming to hold petabytes.
    |
    */
    'max_total_bytes' => env('ZIP_BOMB_GUARD_MAX_BYTES', 100 * 1024 * 1024), // 100 MB

    /*
    |--------------------------------------------------------------------------
    | Maximum entry count
    |--------------------------------------------------------------------------
    |
    | Bombs need thousands of nested entries. Legitimate export bundles
    | rarely need more than a few hundred.
    |
    */
    'max_files' => env('ZIP_BOMB_GUARD_MAX_FILES', 1000),

    /*
    |--------------------------------------------------------------------------
    | Maximum compression ratio (uncompressed : compressed, per entry)
    |--------------------------------------------------------------------------
    |
    | Real files rarely compress better than ~20:1. A 42.zip-style bomb
    | sits around 1,000,000:1.
    |
    */
    'max_ratio' => env('ZIP_BOMB_GUARD_MAX_RATIO', 20),

    /*
    |--------------------------------------------------------------------------
    | Nested archive recursion depth
    |--------------------------------------------------------------------------
    |
    | Archives inside archives are checked recursively (streamed to a temp
    | file under the byte cap first). This caps how deep that goes.
    |
    */
    'max_nesting_depth' => env('ZIP_BOMB_GUARD_MAX_NESTING', 3),

    /*
    |--------------------------------------------------------------------------
    | Reject encrypted (password-protected) entries
    |--------------------------------------------------------------------------
    |
    | Encrypted zips are a favorite malware carrier: scanners and guards
    | cannot inspect what they cannot read.
    |
    */
    'reject_encrypted' => env('ZIP_BOMB_GUARD_REJECT_ENCRYPTED', true),

    /*
    |--------------------------------------------------------------------------
    | Reject symlink entries
    |--------------------------------------------------------------------------
    |
    | A symlink inside the archive can point outside the extraction folder,
    | bypassing every path check. Reject them by default.
    |
    */
    'reject_symlinks' => env('ZIP_BOMB_GUARD_REJECT_SYMLINKS', true),

    /*
    |--------------------------------------------------------------------------
    | Blocked file extensions
    |--------------------------------------------------------------------------
    |
    | Entries with these extensions are rejected — an archive should never
    | drop executable scripts onto your server.
    |
    */
    'blocked_extensions' => ['php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'cmd', 'com', 'scr'],

];
