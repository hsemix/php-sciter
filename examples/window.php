<?php

if (!extension_loaded('ffi')) {
    throw new RuntimeException('PHP FFI extension is not enabled.');
}

$library = dirname(__DIR__) . '/bin/macos/libsciter.dylib';

$cdef = <<<'C'
typedef unsigned int UINT;
typedef unsigned long UINT_PTR;
typedef long INT_PTR;

typedef void* LPVOID;
typedef void* HWINDOW;

typedef struct tagRECT {
    int left;
    int top;
    int right;
    int bottom;
} RECT, *LPRECT;

typedef void (*SCITER_UNUSED_FN)(void);

typedef struct _ISciterAPI {

    UINT version;

    /*
     * Pointer slots 0..29.
     *
     * SciterCreateWindow is slot 30.
     */
    SCITER_UNUSED_FN api_before_create_window[30];

    HWINDOW (*SciterCreateWindow)(
        UINT creationFlags,
        LPRECT frame,
        LPVOID reserved1,
        LPVOID reserved2,
        HWINDOW parent
    );

    /*
     * Slots 31..182.
     *
     * SciterExec is slot 183.
     */
    SCITER_UNUSED_FN api_between_create_and_exec[152];

    INT_PTR (*SciterExec)(
        UINT appCmd,
        UINT_PTR p1,
        UINT_PTR p2
    );

} ISciterAPI;

const ISciterAPI* SciterAPI(void);
C;

$ffi = FFI::cdef($cdef, $library);

$api = $ffi->SciterAPI();

if (FFI::isNull($api)) {
    throw new RuntimeException('SciterAPI() returned NULL.');
}

echo "Sciter API version: {$api->version}" . PHP_EOL;

// SCITER_APP_CMD
const SCITER_APP_STOP     = 0;
const SCITER_APP_LOOP     = 1;
const SCITER_APP_INIT     = 2;
const SCITER_APP_SHUTDOWN = 3;

// SCITER_CREATE_WINDOW_FLAGS
const SW_MAIN = 1 << 7;

$exec = $api->SciterExec;

echo "Initializing Sciter application..." . PHP_EOL;

$result = $exec(
    SCITER_APP_INIT,
    0,
    0
);

echo "APP_INIT returned: {$result}" . PHP_EOL;

// 900 x 600
$frame = $ffi->new('RECT');

$frame->left   = 100;
$frame->top    = 100;
$frame->right  = 1000;
$frame->bottom = 700;

$createWindow = $api->SciterCreateWindow;

echo "Creating Sciter window..." . PHP_EOL;

$window = $createWindow(
    SW_MAIN,
    FFI::addr($frame),
    null,
    null,
    null
);

if (FFI::isNull($window)) {
    throw new RuntimeException(
        'SciterCreateWindow() returned NULL.'
    );
}

echo "Window created successfully." . PHP_EOL;

echo "Starting Sciter event loop..." . PHP_EOL;

/*
 * This blocks until the main window closes.
 */
$exitCode = $exec(
    SCITER_APP_LOOP,
    0,
    0
);

echo "Event loop exited with: {$exitCode}" . PHP_EOL;

echo "Shutting down Sciter..." . PHP_EOL;

$exec(
    SCITER_APP_SHUTDOWN,
    0,
    0
);

echo "Sciter shutdown complete." . PHP_EOL;