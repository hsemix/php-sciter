<?php

declare(strict_types=1);

namespace Sciter\Native;

final class LibraryResolver
{
    public static function resolve(?string $customPath = null): string
    {
        if ($customPath !== null) {
            return self::validate($customPath);
        }

        $os = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Windows' => 'windows',
            'Linux' => 'linux',
            default => throw new \RuntimeException(
                'Unsupported operating system: ' . PHP_OS_FAMILY
            ),
        };

        $architecture = match (strtolower(php_uname('m'))) {
            'arm64', 'aarch64' => 'arm64',
            'x86_64', 'amd64' => 'x64',
            'i386', 'i686' => 'x86',
            default => throw new \RuntimeException(
                'Unsupported architecture: ' . php_uname('m')
            ),
        };

        $filename = match ($os) {
            'macos' => 'libsciter.dylib',
            'linux' => 'libsciter.so',
            'windows' => 'sciter.dll',
        };

        $root = dirname(__DIR__, 2);

        $candidates = [
            "{$root}/bin/{$os}/{$architecture}/{$filename}",
            // Single-file layout, e.g. the universal macOS dylib
            // (x86_64 + arm64) that needs no architecture subfolder.
            "{$root}/bin/{$os}/{$filename}",
            "{$root}/bin/{$filename}",
            "{$root}/{$filename}",
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return self::validate($candidate);
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Sciter native library not found for %s/%s. Searched: %s',
                $os,
                $architecture,
                implode(', ', $candidates)
            )
        );
    }

    private static function validate(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved === false || !is_file($resolved)) {
            throw new \RuntimeException(
                "Sciter native library not found: {$path}"
            );
        }

        if (!is_readable($resolved)) {
            throw new \RuntimeException(
                "Sciter native library is not readable: {$resolved}"
            );
        }

        return $resolved;
    }
}
