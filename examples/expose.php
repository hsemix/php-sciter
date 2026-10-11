<?php

require __DIR__ . '/../vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Window;

$runtime = new Runtime();

$window = new Window($runtime);

$window->expose('app', [
    'greet' => fn (string $name): string =>
        "Hello, {$name}!",

    'add' => fn (int $a, int $b): int =>
        $a + $b,

    'getUser' => fn (): array => [
        'name' => 'Hamid',
        'role' => 'admin',
        'permissions' => [
            'users.read',
            'users.create',
        ],
    ],
]);

$html = <<<'HTML'
<html>
<head>
    <style>
        body {
            font-family: system-ui;
            padding: 40px;
        }
    </style>
</head>
<body>
    <h1>PHP ↔ Sciter</h1>
    <p id="result">Waiting...</p>

    <script>
        const greeting = app.greet("Hamid");
        const sum = app.add(10, 20);
        const user = app.getUser();

        document.getElementById("result").textContent =
            `${greeting} Sum: ${sum}. Role: ${user.role}`;

        console.log("User:", JSON.stringify(user));
    </script>
</body>
</html>
HTML;

$window
    ->loadHtml($html)
    ->show();



try {
    $runtime->run();
} finally {
    $runtime->shutdown();
}

echo "Sciter event loop finished.\n";