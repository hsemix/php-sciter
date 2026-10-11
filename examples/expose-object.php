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

    #[Expose('sum')]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[Expose]
    public function getUser(): array
    {
        return [
            'name' => 'Hamid',
            'role' => 'admin',
            'permissions' => ['users.read', 'users.create'],
        ];
    }

    public function internalOperation(): string
    {
        return 'This must not be accessible from JavaScript.';
    }
}

$runtime = new Runtime();
$window = new Window($runtime);

$window->expose('app', new AppController());

$html = <<<'HTML'
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
    </style>
</head>
<body>
    <h2>PHP Object Exposure Test</h2>
    <div id="results"></div>

    <script>
        const results = document.$('#results');

        function report(name, passed, value) {
            const row = document.createElement('div');

            row.className = passed ? 'success' : 'failure';

            row.textContent =
                (passed ? 'PASS: ' : 'FAIL: ') +
                name + ' — ' + JSON.stringify(value);

            results.append(row);
            console.log(row.textContent);
        }

        async function runTests() {
            try {
                const greeting = await globalThis.app.greet('Hamid');

                report(
                    'greet',
                    greeting === 'Hello, Hamid!',
                    greeting
                );

                const sum = await globalThis.app.sum(10, 20);

                report('renamed add -> sum', sum === 30, sum);

                const user = await globalThis.app.getUser();

                report(
                    'getUser',
                    user.name === 'Hamid' &&
                    user.role === 'admin' &&
                    user.permissions[1] === 'users.create',
                    user
                );

                report(
                    'internalOperation hidden',
                    typeof globalThis.app.internalOperation === 'undefined',
                    typeof globalThis.app.internalOperation
                );
            } catch (error) {
                report('Unexpected error', false, String(error));
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

echo "Object exposure test completed.\n";
