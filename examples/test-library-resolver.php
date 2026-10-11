<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Native\LibraryResolver;

echo "OS: " . PHP_OS_FAMILY . PHP_EOL;
echo "Architecture: " . php_uname('m') . PHP_EOL;

try {
    $library = LibraryResolver::resolve();

    echo "Resolved library: {$library}" . PHP_EOL;
    echo "Library resolution successful." . PHP_EOL;
} catch (Throwable $exception) {
    echo "Resolution failed: {$exception->getMessage()}" . PHP_EOL;
    exit(1);
}