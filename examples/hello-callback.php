<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
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
$greet = function (
    $thing,
    int $argc,
    $argv,
    $result
) use ($values): int {
    try {
        echo "greet() called with {$argc} argument(s)" . PHP_EOL;

        if ($argc < 1) {
            return 0;
        }

        /*
         * argv itself is const VALUE* and therefore points
         * to argv[0].
         */
        $name = $values->toString($argv);

        echo "PHP received: {$name}" . PHP_EOL;

        /*
         * result is already VALUE*.
         */
        $values->setString(
            $result,
            "Hello {$name}, from PHP!"
        );

        echo "PHP result prepared." . PHP_EOL;

        return 1;
    } catch (\Throwable $e) {
        echo "PHP callback error: "
            . $e::class
            . ': '
            . $e->getMessage()
            . PHP_EOL;

        return 0;
    }
};


$methods = $ffi->new('som_method_def_t[1]');

$methods[0]->reserved = null;
$methods[0]->name = $greetName;
// $methods[0]->params = 0;
$methods[0]->params = 1;
$methods[0]->func = $greet;

$passport = $ffi->new('som_passport_t');

$passport->flags = 0;
$passport->name = $bridgeName;

$passport->properties = null;
$passport->n_properties = 0;

$passport->methods = FFI::addr($methods[0]);
$passport->n_methods = 1;

$passport->item_getter = null;
$passport->item_setter = null;
$passport->item_next = null;
$passport->prop_getter = null;
$passport->prop_setter = null;
$passport->name_resolver = null;
$passport->reserved = null;

$references = 1;

$assetClass = $ffi->new('som_asset_class_t');

$assetClass->asset_add_ref =
    function ($thing) use (&$references): int {
        return ++$references;
    };

$assetClass->asset_release =
    function ($thing) use (&$references): int {
        return --$references;
    };

$assetClass->asset_get_interface =
    function ($thing, $name, $out): int {
        return 0;
    };

$assetClass->asset_get_passport =
    function ($thing) use (&$passport) {
        return FFI::addr($passport);
    };

$asset = $ffi->new('som_asset_t');

$asset->isa = FFI::addr($assetClass);

$setGlobalAsset = $api->SciterSetGlobalAsset;

$registered = $setGlobalAsset(
    FFI::addr($asset)
);

echo "Global PHP asset registered: "
    . ($registered ? 'yes' : 'no')
    . PHP_EOL;





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

    const message = php.greet("Hamid 👋");

    console.log("PHP returned:", message);

    result.innerText = message;
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

$runtime->run();