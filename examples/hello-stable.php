<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\FFI\SciterApi;

$library = dirname(__DIR__)
    . '/bin/macos/libsciter.dylib';

$sciter = new SciterApi($library);

$ffi = $sciter->ffi();
$api = $sciter->api();

const SCITER_APP_LOOP = 1;
const SCITER_APP_INIT = 2;

const SW_CHILD       = 1 << 0;
const SW_TITLEBAR    = 1 << 1;
const SW_RESIZEABLE  = 1 << 2;
const SW_TOOL        = 1 << 3;
const SW_CONTROLS    = 1 << 4;
const SW_GLASSY      = 1 << 5;
const SW_ALPHA       = 1 << 6;
const SW_MAIN        = 1 << 7;
const SW_ENABLE_DEBUG = 1 << 8;

const WINDOW_FLAGS =
    SW_MAIN |
    SW_TITLEBAR |
    SW_RESIZEABLE |
    SW_CONTROLS;

/*
 * Initialize Sciter.
 */
$exec = $api->SciterExec;

$result = $exec(
    SCITER_APP_INIT,
    0,
    0
);

echo "APP_INIT: {$result}" . PHP_EOL;

/*
 * Create the window.
 */
$frame = $ffi->new('RECT');

$frame->left   = 100;
$frame->top    = 100;
$frame->right  = 1000;
$frame->bottom = 700;

$createWindow = $api->SciterCreateWindow;

$window = $createWindow(
    WINDOW_FLAGS,
    FFI::addr($frame),
    null,
    null,
    null
);

if (FFI::isNull($window)) {
    throw new RuntimeException(
        'Failed to create Sciter window.'
    );
}

/*
 * Our first actual UI.
 */
$html = <<<'HTML'
<!doctype html>
<html>
<head>
    <title>PHP Sciter</title>

    <style>
        html {
            background: #f5f5f5;
            color: #222;
            font-family: system;
        }

        body {
            margin: 0;
            padding: 60px;
        }

        h1 {
            font-size: 42px;
        }

        p {
            font-size: 18px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
        }
    </style>
</head>

<body>

<div class="card">
    <h1>Hello from PHP 👋</h1>

    <p>
        This native Sciter window was created
        directly from PHP through FFI.
    </p>

    <p>
        No C/C++ bridge involved.
    </p>
</div>

</body>
</html>
HTML;

$buffer = $ffi->new(
    'BYTE[' . strlen($html) . ']'
);

FFI::memcpy(
    $buffer,
    $html,
    strlen($html)
);

$loadHtml = $api->SciterLoadHtml;

$loaded = $loadHtml(
    $window,
    $buffer,
    strlen($html),
    null
);

if (!$loaded) {
    throw new RuntimeException(
        'SciterLoadHtml() failed.'
    );
}

echo "HTML loaded." . PHP_EOL;


/*
 * Show the native window.
 */
const SCITER_WINDOW_SET_STATE = 1;
const SCITER_WINDOW_STATE_SHOWN = 2;

$windowExec = $api->SciterWindowExec;

$windowExec(
    $window,
    SCITER_WINDOW_SET_STATE,
    SCITER_WINDOW_STATE_SHOWN,
    0
);

echo "Window shown." . PHP_EOL;


/*
 * Run application.
 */
echo "Starting application..." . PHP_EOL;

$exec(
    SCITER_APP_LOOP,
    0,
    0
);