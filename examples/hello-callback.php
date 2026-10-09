<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Som\Asset;
use Sciter\Value;

$runtime = new Runtime();

$runtime->initialize();

$ffi = $runtime->ffi();
$api = $runtime->api();

$values = new Value($runtime);

const SW_MAIN = 1 << 7;


/*
 * Register PHP SOM bridge.
 */

$atomValue = $api->SciterAtomValue;

$bridgeName = $atomValue('php');
$greetName  = $atomValue('greet');

echo "php atom: {$bridgeName}" . PHP_EOL;
echo "greet atom: {$greetName}" . PHP_EOL;


$php = new Asset($runtime, 'php');

$php->method(
    'greet',
    function ($argc, $argv, $result) use ($values): bool {
        $name = $values->toString($argv);

        echo "PHP received: {$name}" . PHP_EOL;

        $values->setString(
            $result,
            "Hello {$name}, from PHP!"
        );

        return true;
    },
    1
);

$php->register();

echo "Global PHP asset registered: yes" . PHP_EOL;




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
    SW_MAIN,
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

    <h1>PHP ↔ Sciter</h1>

    <p>
        Click the button to invoke PHP
        from Sciter script.
    </p>

    <button id="call-php">
        Call PHP
    </button>

    <h2 id="result"></h2>

</div>

<script type="module">

const result = document.$("#result");

document.$("#call-php").on("click", () => {

    // const message = php.greet("Hamid 👋");

    // console.log("PHP returned:", message);

    // document.$("#result").text = message;

    // const message = php.greet("Hamid 👋");

    // console.log("PHP returned:", message);

    // result.innerText = message;

    result.innerText = "SOM callback deliberately skipped";
});

</script>

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
echo "Starting application..." . PHP_EOL;

try {
    $runtime->run();
} finally {
    echo "Sciter event loop finished." . PHP_EOL;

    // $php->unregister();

    echo "Leaving SOM asset registered for shutdown test." . PHP_EOL;
}