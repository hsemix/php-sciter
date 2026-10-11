<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Window;
use Sciter\WindowOptions;

$runtime = new Runtime();

$main = new Window(
    $runtime,
    new WindowOptions(
        width: 800,
        height: 550,
        x: 100,
        y: 100,
        main: true
    )
);

$secondary = new Window(
    $runtime,
    new WindowOptions(
        width: 500,
        height: 350,
        x: 950,
        y: 150,
        main: false,
        resizable: false,
        titlebar: false,
        controls: false
    )
);

$mainHtml = <<<'HTML'
<html>
<head>
    <title>PHP Sciter - Main Window</title>
</head>
<body style="font:system;padding:24dip">
    <h2>Main Window</h2>
    <p>This window should be resizable.</p>
</body>
</html>
HTML;

$secondaryHtml = <<<'HTML'
<html>
<head>
    <title>PHP Sciter - Secondary Window</title>
</head>
<body style="font:system;padding:24dip">
    <h2>Secondary Window</h2>
    <p>This window should not be resizable.</p>
</body>
</html>
HTML;

try {
    $main->loadHtml($mainHtml)->show();
    $secondary->loadHtml($secondaryHtml)->show();

    $runtime->run();
} finally {
    $runtime->shutdown();
}

echo "Window configuration test completed.\n";
