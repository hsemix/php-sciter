<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\WindowOptions;

$runtime = new Runtime();

$main = $runtime->createWindow(
    new WindowOptions(
        width: 700,
        height: 450,
        main: true
    )
);

$secondary = $runtime->createWindow(
    new WindowOptions(
        width: 450,
        height: 300,
        x: 850,
        y: 150,
        main: false
    )
);

$mainHtml = <<<'HTML'
<html>
<head>
    <title>Main Window</title>
</head>
<body style="font:system;padding:24dip">
    <h2>Main Window</h2>
    <p>This window should remain visible.</p>
</body>
</html>
HTML;

$secondaryHtml = <<<'HTML'
<html>
<head>
    <title>Secondary Window</title>
</head>
<body style="font:system;padding:24dip">
    <h2>Secondary Window</h2>
    <p>This window will be hidden before the event loop starts.</p>
</body>
</html>
HTML;

try {
    $main->loadHtml($mainHtml)->show();

    $secondary->loadHtml($secondaryHtml)->show();

    echo "Initial state: {$secondary->state()}\n";

    $secondary->hide();

    echo "After hide: {$secondary->state()}\n";

    $secondary->show();

    echo "After show: {$secondary->state()}\n";

    $secondary->minimize();
    echo "After minimize: {$secondary->state()}\n";

    $secondary->restore();
    echo "After restore: {$secondary->state()}\n";

    $secondary->maximize();
    echo "After maximize: {$secondary->state()}\n";

    $secondary->restore();
    echo "After second restore: {$secondary->state()}\n";

    $runtime->run();
} finally {
    $runtime->shutdown();
}