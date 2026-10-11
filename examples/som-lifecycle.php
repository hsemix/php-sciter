<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Window;

$runtime = new Runtime();

$window = new Window($runtime);

$calls = [
    'app.greet' => 0,
    'app.add' => 0,
    'system.status' => 0,
    'system.echo' => 0,
];

$window->expose('app', [
    'greet' => function (string $name) use (&$calls): string {
        $calls['app.greet']++;
        return "Hello, {$name}!";
    },

    'add' => function (int $a, int $b) use (&$calls): int {
        $calls['app.add']++;
        return $a + $b;
    },
]);

$window->expose('system', [
    'status' => function () use (&$calls): array {
        $calls['system.status']++;

        return [
            'running' => true,
            'version' => '1.0',
        ];
    },

    'echo' => function (array $data) use (&$calls): array {
        $calls['system.echo']++;
        return $data;
    },
]);

$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font: system;
            padding: 24dip;
        }

        .success {
            color: green;
        }

        .failure {
            color: red;
        }

        .result {
            margin: 8dip 0;
        }
    </style>
</head>
<body>
    <h2>SOM Lifecycle Regression Test</h2>

    <div id="results"></div>

    <script>
        const results = document.$('#results');

        function assert(name, condition, actual) {
            const row = document.createElement('div');
            row.className = condition
                ? 'result success'
                : 'result failure';

            row.textContent =
                (condition ? 'PASS: ' : 'FAIL: ') +
                name +
                ' — ' +
                JSON.stringify(actual);

            results.append(row);

            console.log(row.textContent);
        }

        async function runTests() {
            try {
                const greeting = await globalThis.app.greet('Hamid');

                assert(
                    'app.greet',
                    greeting === 'Hello, Hamid!',
                    greeting
                );

                const sum = await globalThis.app.add(10, 20);

                assert(
                    'app.add',
                    sum === 30,
                    sum
                );

                const status = await globalThis.system.status();

                assert(
                    'system.status',
                    status.running === true &&
                    status.version === '1.0',
                    status
                );

                const payload = {
                    name: 'Sciter',
                    values: [10, 20, 30],
                    nested: {
                        enabled: true
                    }
                };

                const echoed = await globalThis.system.echo(payload);

                assert(
                    'system.echo',
                    echoed.name === 'Sciter' &&
                    echoed.values[2] === 30 &&
                    echoed.nested.enabled === true,
                    echoed
                );
            } catch (error) {
                assert('Unexpected JS error', false, String(error));
            }
        }

        runTests();
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

echo "\n[TEST] PHP callback counts:\n";

foreach ($calls as $method => $count) {
    echo sprintf(
        "[TEST] %-20s %d call(s)\n",
        $method,
        $count
    );
}

$passed = true;

foreach ($calls as $method => $count) {
    if ($count !== 1) {
        fwrite(
            STDERR,
            "[FAIL] {$method}: expected 1 call, got {$count}\n"
        );

        $passed = false;
    }
}

if (!$passed) {
    exit(1);
}

echo "[PASS] All PHP callbacks executed exactly once.\n";
echo "[PASS] Sciter shutdown completed.\n";
