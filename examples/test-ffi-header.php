<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\FFI\Definitions;

try {
    $ffi = Definitions::load();

    echo "FFI header loaded successfully.\n";

    echo "ISciterAPI size: "
        . \FFI::sizeof($ffi->new('ISciterAPI'))
        . " bytes\n";

} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}