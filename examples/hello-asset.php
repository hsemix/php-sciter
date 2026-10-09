<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// use FFI;
use Sciter\Runtime;
use Sciter\Som\Asset;

$runtime = new Runtime();
$runtime->initialize();

$php = new Asset($runtime, 'php');

$php->method(
    'greet',
    static function ($argc, $argv, $result): bool {
        echo "greet() registered with {$argc} arguments" . PHP_EOL;

        return true;
    },
    1
);

$passport = $php->passport();

echo 'Asset atom: ', $php->atom(), PHP_EOL;
echo 'Passport atom: ', $passport->name, PHP_EOL;
echo 'Method count: ', $passport->n_methods, PHP_EOL;

echo 'Methods pointer null: ',
    FFI::isNull($passport->methods) ? 'yes' : 'no',
    PHP_EOL;

echo 'First method atom: ',
    $passport->methods[0]->name,
    PHP_EOL;

echo 'Expected greet atom: ',
    ($runtime->api()->SciterAtomValue)('greet'),
    PHP_EOL;

echo 'Parameter count: ',
    $passport->methods[0]->params,
    PHP_EOL;

echo 'Callback null: ',
    FFI::isNull($passport->methods[0]->func) ? 'yes' : 'no',
    PHP_EOL;