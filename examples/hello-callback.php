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

$value = new Value($runtime);

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

$php
    ->method('greet', fn(string $name): string => "Hello, {$name}!")
    ->method('add', fn(int $a, int $b): int => $a + $b)
    ->method('multiply', fn(float $a, float $b): float => $a * $b)
    ->method('negate', fn(bool $value): bool => !$value)
    ->method('returnNull', fn(): mixed => null)
    ->method('isNull', fn(?string $value): bool => $value === null)

    ->method(
        'getNumbers',
        fn(): array => [10, 20, 30, 40]
    )
    ->method(
        'sumNumbers',
        fn(array $numbers): int => array_sum($numbers)
    )

    ->method('getUser', fn(): array => [
        'id' => 1,
        'name' => 'Hamid',
        'active' => true,
        'roles' => ['admin', 'cashier'],
    ])

    ->register();

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

// const result = document.$("#result");

// document.$("#call-php").on("click", () => {

    try {
        const greeting = php.greet("Hamid");

        console.log(greeting);

        // document.querySelector("#result").textContent = greeting;

        // const sum = php.add(10, 20);

        // console.log("Sum:", sum);

        // document.querySelector("#result").textContent =
        //     `Greeting: ${php.greet("Hamid")} | Sum: ${sum}`;

        // for (let i = 0; i < 100; i++) {
        //     const greeting = php.greet("Hamid");
        //     const sum = php.add(i, 20);

        //     if (greeting !== "Hello, Hamid!" || sum !== i + 20) {
        //         throw new Error(`Unexpected result at iteration ${i}`);
        //     }
        // }

        // console.log("All 100 iterations passed.");

        const product = php.multiply(2.5, 4.25);
        const negated = php.negate(true);

        console.log("Product:", product);
        console.log("Negated:", negated);

        document.querySelector("#result").textContent =
            `Greeting: ${php.greet("Hamid")} | ` +
            `Sum: ${php.add(10, 20)} | ` +
            `Product: ${product} | ` +
            `Negated: ${negated}`;
            } catch (error) {
                console.error(error);
            }

        const a = php.returnNull();
        const b = php.isNull(null);
        const c = php.isNull(undefined);

        console.log("Returned null:", a === null);
        console.log("Accepted null:", b);
        console.log("Accepted undefined:", c);


        const numbers = php.getNumbers();

        console.log("Numbers:", JSON.stringify(numbers));
        console.log("Sum:", php.sumNumbers([10, 20, 30, 40]));
        console.log("Third number:", numbers[2]);


        const user = php.getUser();

        console.log(user.name);       // Hamid
        console.log(user.roles[0]);   // admin
// });

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
    // echo "Sciter event loop finished." . PHP_EOL;

    // // $php->unregister();

    // echo "Leaving SOM asset registered for shutdown test." . PHP_EOL;


    echo "Sciter event loop finished." . PHP_EOL;

    echo "Releasing SOM asset..." . PHP_EOL;
    $php->unregister();

    echo "Shutting down Sciter..." . PHP_EOL;
    $runtime->shutdown();

    echo "Shutdown sequence completed." . PHP_EOL;

    echo "Final SOM references: {$php->references()}" . PHP_EOL;
}

register_shutdown_function(static function (): void {
    fwrite(STDERR, "[PHP] Shutdown function called\n");
});
