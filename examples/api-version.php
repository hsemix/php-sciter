<?php

if (!extension_loaded('ffi')) {
    throw new RuntimeException('PHP FFI extension is not enabled.');
}

$library = dirname(__DIR__) . '/bin/macos/libsciter.dylib';

$cdef = <<<'C'
typedef unsigned int UINT;
typedef int SBOOL;

/*
 * Generic function pointer.
 *
 * For fields we are not calling yet, we only care that each occupies
 * one function-pointer slot in ISciterAPI.
 */
typedef void (*SCITER_UNUSED_FN)(void);

typedef struct _ISciterAPI {

    UINT version;

    SCITER_UNUSED_FN SciterClassName;

    UINT (*SciterVersion)(SBOOL major);

} ISciterAPI;

const ISciterAPI* SciterAPI(void);
C;

$ffi = FFI::cdef($cdef, $library);

$api = $ffi->SciterAPI();

if (FFI::isNull($api)) {
    throw new RuntimeException('SciterAPI() returned NULL.');
}

echo "API version: {$api->version}" . PHP_EOL;

$sciterVersion = $api->SciterVersion;

$major = $sciterVersion(1);
$minor = $sciterVersion(0);

echo "SciterVersion(true):  {$major}" . PHP_EOL;
echo "SciterVersion(false): {$minor}" . PHP_EOL;