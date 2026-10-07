<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Window;

$runtime = new Runtime();

$window = new Window(
    runtime: $runtime,
    width: 900,
    height: 600
);

$window
    ->loadHtml(<<<'HTML'
<!doctype html>
<html>
<head>
    <title>PHP Sciter</title>

    <style>
        html {
            background: #f5f5f5;
            font-family: system;
            color: #222;
        }

        body {
            padding: 60px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        h1 {
            font-size: 42px;
        }
    </style>
</head>

<body>

<div class="card">
    <h1>PHP Sciter 🚀</h1>

    <p>
        The FFI implementation is now hidden
        behind PHP objects.
    </p>
</div>

</body>
</html>
HTML)
    ->show();

$runtime->run();