<?php

namespace ZipBombGuard\Rules;

use Closure;
use ZipBombGuard\ArchiveSafetyChecker;
use ZipBombGuard\Exceptions\UnsafeArchiveException;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Drop-in validation rule for archive uploads.
 *
 * Usage:
 *   $request->validate([
 *       'archive' => ['required', 'file', 'mimes:zip', new NoZipBomb],
 *   ]);
 */
class NoZipBomb implements ValidationRule
{
    public function __construct(
        protected ?ArchiveSafetyChecker $checker = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = $value instanceof File ? $value->getRealPath() : (is_string($value) ? $value : null);

        if (! is_string($path) || ! is_file($path)) {
            $fail('The :attribute must be a valid archive file.');

            return;
        }

        $checker = $this->checker ?? app(ArchiveSafetyChecker::class);

        try {
            $checker->assertSafe($path);
        } catch (UnsafeArchiveException $e) {
            $fail('The :attribute archive was rejected: '.$e->getMessage());
        }
    }
}
