<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Attributes\Expose;
use Sciter\Runtime;
use Sciter\Window;

final class AppController
{
    #[Expose]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Expose]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

$runtime = new Runtime();

$runtime->expose('app', new AppController());

$runtime->expose('system', [
    'status' => fn(): array => [
        'running' => true,
        'framework' => 'PHP Sciter',
    ],
]);

try {
    $runtime->expose('app', [
        'test' => fn() => 'duplicate',
    ]);

    throw new \RuntimeException(
        'Duplicate asset registration was unexpectedly allowed.'
    );
} catch (\LogicException $e) {
    echo "[PASS] Duplicate asset rejected.\n";
}

$window = new Window($runtime);

$html = <<<'HTML'
<html>
<head>
    <style>
        body {
            font: system;
            padding: 24dip;
        }
    </style>
</head>
<body>
    <h2>Runtime Asset Exposure</h2>
    <div id="result">Running tests...</div>

    <script>
        async function test() {
            const greeting = await globalThis.app.greet('Hamid');
            const sum = await globalThis.app.add(10, 20);
            const status = await globalThis.system.status();

            const passed =
                greeting === 'Hello, Hamid!' &&
                sum === 30 &&
                status.running === true;

            document.$('#result').textContent = passed
                ? 'PASS: Runtime exposure works'
                : 'FAIL: Runtime exposure failed';

            console.log({
                greeting,
                sum,
                status,
                passed
            });
        }

        test();
    </script>
</body>
</html>
HTML;

try {
    $window->loadHtml($html)->show();
    $runtime->run();
} finally {
    $runtime->shutdown();
}

echo "Runtime exposure test completed.\n";
