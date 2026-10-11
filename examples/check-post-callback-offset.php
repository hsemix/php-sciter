<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;

$runtime = new Runtime();
$runtime->initialize();

$ffi = $runtime->ffi();
$api = $runtime->api();

$base = $ffi->cast('uintptr_t', $api)->cdata;

$fields = [
    'SciterSetCallback',
    'SciterCreateWindow',
    'ValueInit',
    'ValueStringData',
    'ValueStringDataSet',
    'SciterGetCallbackParam',
    'SciterPostCallback',
    'SciterAtomValue',
    'SciterSetGlobalAsset',
];

foreach ($fields as $field) {
    $address = $ffi->cast(
        'uintptr_t',
        FFI::addr($api->$field)
    )->cdata;

    $offset = $address - $base;

    printf(
        "%s: offset=%d, slot=%d\n",
        $field,
        $offset,
        $offset / FFI::sizeof($ffi->new('void*'))
    );
}
