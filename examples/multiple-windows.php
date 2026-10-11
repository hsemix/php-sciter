<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Attributes\Expose;
use Sciter\Runtime;
use Sciter\Window;

final class SharedController
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

$runtime = new Runtime();

$controller = new SharedController();

// Register the service once for the entire runtime.
$runtime->expose('app', $controller);

function windowHtml(string $title): string
{
    $title = htmlspecialchars(
        $title,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return <<<HTML
<html>
<head>
    <style>
        body {
            font: system;
            padding: 24dip;
        }

        button {
            padding: 10dip 16dip;
            margin: 8dip 0;
        }
    </style>
</head>
<body>
    <h2>{$title}</h2>

    <p id="greeting">Loading...</p>
    <p>Shared counter: <strong id="counter">0</strong></p>

    <button id="increment">Increment shared counter</button>
    <button id="refresh">Refresh counter</button>

    <script>
        async function refresh() {
            const count = await globalThis.app.getCounter();
            document.\$('#counter').textContent = String(count);
        }

        document.\$('#increment').on('click', async function() {
            const count = await globalThis.app.increment();
            document.\$('#counter').textContent = String(count);
            console.log('Counter: ' + count);
        });

        document.\$('#refresh').on('click', refresh);

        async function initialize() {
            const greeting = await globalThis.app.greet('Hamid');
            document.\$('#greeting').textContent = greeting;
            await refresh();
        }

        initialize();
    </script>
</body>
</html>
HTML;
}

$window1 = new Window(
    $runtime,
    width: 600,
    height: 400,
    x: 100,
    y: 100
);

$window2 = new Window(
    $runtime,
    width: 600,
    height: 400,
    x: 750,
    y: 100,
    main: false
);

try {
    $window1->loadHtml(
        windowHtml('Window One')
    )->show();

    $window2->loadHtml(
        windowHtml('Window Two')
    )->show();

    $runtime->run();
} finally {
    $runtime->shutdown();
}

echo "Final shared counter: {$controller->getCounter()}\n";
echo "Multiple-window test completed.\n";
