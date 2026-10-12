<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Events\EventDispatcher;

$events = new EventDispatcher();

$results = [];

$first = $events->on('close', function (string $name) use (&$results) {
    $results[] = "first:$name";
});

$second = $events->on('close', function (string $name) use (&$results) {
    $results[] = "second:$name";
});

$events->emit('close', 'Window A');

assert($results === [
    'first:Window A',
    'second:Window A',
]);

assert($events->listenerCount('close') === 2);

// Remove the first listener
assert($events->off('close', $first) === true);

$results = [];

$events->emit('close', 'Window B');

assert($results === [
    'second:Window B',
]);

assert($events->listenerCount('close') === 1);

// Removing an already removed listener
assert($events->off('close', $first) === false);

// Clear listeners
$events->removeAllListeners('close');

assert($events->listenerCount('close') === 0);

// Test listener removal during dispatch
$results = [];

$events->on('test', function () use (&$results, $events, &$second) {
    $results[] = 'one';
    $events->off('test', $second);
});

$second = $events->on('test', function () use (&$results) {
    $results[] = 'two';
});

$events->emit('test');

assert($results === ['one', 'two']);

$results = [];

$events->emit('test');

assert($results === ['one']);

echo "All event dispatcher tests passed.\n";
