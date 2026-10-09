<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// use FFI;
use Sciter\Runtime;

$runtime = new Runtime();
$runtime->initialize();

$api = $runtime->api();

$atom = ($api->SciterAtomValue)('php');
$greetAtom = ($api->SciterAtomValue)('greet');

$native = FFI::cdef(
    <<<'C'
    typedef struct som_asset_t som_asset_t;

    // som_asset_t* som_probe_create(unsigned long long name_atom);
    long som_probe_refs(som_asset_t* thing);

    void som_probe_ping(void);

    int som_probe_pin_library(void);

    som_asset_t* som_probe_create(
        unsigned long long name_atom,
        unsigned long long greet_atom
    );

    typedef struct {
        uint32_t t;
        uint32_t u;
        uint64_t d;
    } SCITER_VALUE;

    typedef unsigned int (*ValueStringDataFn)(
        const SCITER_VALUE* value,
        const uint16_t** text,
        unsigned int* length
    );

    void som_probe_set_value_string_data(uintptr_t address);
    void som_probe_set_value_string_data_set(uintptr_t address);


    typedef void (*PhpGreetCallback)(
        const char* name,
        char* output,
        size_t output_size
    );

    void som_probe_set_greet_callback(PhpGreetCallback callback);


    C,
    dirname(__DIR__) . '/tests/native/libsom_probe.dylib'
);


$phpGreetCallback = static function (
    string $name,
    $output,
    int $outputSize
): void {
    $greeting = "Hello, {$name}!";

    $bytes = substr(
        $greeting,
        0,
        max(0, $outputSize - 1)
    );

    $length = strlen($bytes);

    if ($length > 0) {
        FFI::memcpy($output, $bytes, $length);
    }

    $output[$length] = "\0";

    echo "[PHP] Generated greeting: {$greeting}" . PHP_EOL;
};

$native->som_probe_set_greet_callback($phpGreetCallback);




$ffi = $runtime->ffi();

$function = $api->ValueStringData;

$setFunction = $api->ValueStringDataSet;

$setPointer = $ffi->cast('uintptr_t', $setFunction);

$native->som_probe_set_value_string_data_set(
    $setPointer->cdata
);


$pointer = $ffi->cast('uintptr_t', $function);

$native->som_probe_set_value_string_data(
    $pointer->cdata
);

if ($native->som_probe_pin_library() !== 1) {
    throw new RuntimeException(
        'Failed to pin native SOM library.'
    );
}

$asset = $native->som_probe_create($atom, $greetAtom);

if (FFI::isNull($asset)) {
    throw new RuntimeException('Failed to create native SOM asset.');
}

/*
 * Register a fully native SOM asset.
 */
// $ffi = $runtime->ffi();

$address = $native->cast('uintptr_t', $asset);

$assetPtr = $ffi->cast('som_asset_t*', $address);

$registered = ($api->SciterSetGlobalAsset)($assetPtr);

if (!$registered) {
    throw new RuntimeException('Failed to register native SOM asset.');
}

echo "Native SOM asset registered." . PHP_EOL;

$ffi = $runtime->ffi();

$frame = $ffi->new('RECT');
$frame->left = 100;
$frame->top = 100;
$frame->right = 900;
$frame->bottom = 600;

$window = ($api->SciterCreateWindow)(
    1 << 7,
    FFI::addr($frame),
    null,
    null,
    null
);

if (FFI::isNull($window)) {
    throw new RuntimeException('Failed to create Sciter window.');
}

$html = <<<'HTML'
<html>
<body>
    <h1>Native SOM Method Test</h1>
    <p id="result">Waiting for native method...</p>

    <script>
        try {

        const greeting = php.greet("Hamid");

            document.getElementById("result").textContent = greeting;
            // php.greet("Hamid");

            // document.getElementById("result").textContent =
            //     "Native greet() invoked.";
        } catch (error) {
            document.getElementById("result").textContent =
                "Error: " + error;
        }
    </script>
</body>
</html>
HTML;

$buffer = $ffi->new('BYTE[' . strlen($html) . ']');
FFI::memcpy($buffer, $html, strlen($html));

($api->SciterLoadHtml)(
    $window,
    $buffer,
    strlen($html),
    null
);

($api->SciterWindowExec)(
    $window,
    1,
    2,
    0
);

echo "Window shown." . PHP_EOL;

/*
 * Keep both FFI handles alive during normal execution.
 */
$runtime->run();

echo "Sciter event loop finished." . PHP_EOL;
$native->som_probe_ping();

echo "Native references remaining: "
    . $native->som_probe_refs($asset)
    . PHP_EOL;

$released = ($api->SciterReleaseGlobalAsset)($assetPtr);

echo "Global asset released: "
    . ($released ? 'yes' : 'no')
    . PHP_EOL;

// echo "Native references remaining: "
//     . $native->som_probe_refs($asset)
//     . PHP_EOL;

// // Keep native objects referenced until PHP shutdown.
// $GLOBALS['sciter_native_probe'] = [
//     'runtime' => $runtime,
//     'native' => $native,
//     'asset' => $asset,
//     'assetPtr' => $assetPtr,
// ];

// echo "Ready for shutdown." . PHP_EOL;