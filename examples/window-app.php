<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Application;

$app = new Application();

$window = $app->window(
    width: 900,
    height: 600,
);

$window->loadHtml(<<<'HTML'
<!doctype html>
<html>
<head>
    <title>My PHP App</title>

    <style>
        html {
            font-family: system;
            background: #f5f5f5;
        }

        body {
            padding: 60px;
        }

        .card {
            background: white;
            padding: 32px;
            border-radius: 12px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Hello PHP Desktop 👋</h1>
        <p>Sciter is now hidden behind a PHP API.</p>
    </div>
</body>
</html>
HTML);

$app->run($window);