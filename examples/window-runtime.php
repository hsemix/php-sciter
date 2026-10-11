<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Attributes\Expose;
use Sciter\Runtime;
use Sciter\WindowOptions;

final class AppController
{
    private int $counter = 0;

    #[Expose]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Expose]
    public function increment(): int
    {
        return ++$this->counter;
    }

    #[Expose]
    public function getCounter(): int
    {
        return $this->counter;
    }
}

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

$settingsHtml = <<<'HTML'
<html>
<head>
    <title>PHP Sciter - Settings Window</title>
</head>
<body style="font:system;padding:24dip">
    <h2>Settings Window</h2>
    <p>This window should not be resizable.</p>
</body>
</html>
HTML;

$runtime = new Runtime();

$runtime->expose('app', new AppController());

$main = $runtime->createWindow(
    new WindowOptions(
        width: 1000,
        height: 700,
        main: true
    )
);

$settings = $runtime->createWindow(
    new WindowOptions(
        width: 600,
        height: 450,
        main: false
    )
);

try {
    $main->loadHtml($mainHtml)->show();
    $settings->loadHtml($settingsHtml)->show();

    $runtime->run();
} finally {
    $runtime->shutdown();
}