<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Events\EventDispatcher;

$events = new EventDispatcher();

$count = 0;

$events->once('test', function () use (&$count) {
    $count++;
});

$events->emit('test');
$events->emit('test');
$events->emit('test');

assert($count === 1);

echo "Basic once listener passed.\n";

// Test recursive dispatch
$recursiveCount = 0;

$events->once('recursive', function () use (
    &$recursiveCount,
    $events
) {
    $recursiveCount++;

    // This should not invoke the once listener again.
    $events->emit('recursive');
});

$events->emit('recursive');

assert($recursiveCount === 1);

echo "Recursive once listener passed.\n";

// Test removing a once listener before it executes.
$removedCount = 0;

$id = $events->once('removed', function () use (&$removedCount) {
    $removedCount++;
});

assert($events->off('removed', $id) === true);

$events->emit('removed');

assert($removedCount === 0);

echo "Once listener removal passed.\n";

// Verify listener cleanup.
assert($events->listenerCount('test') === 0);
assert($events->listenerCount('recursive') === 0);
assert($events->listenerCount('removed') === 0);

echo "All once listener tests passed.\n";
