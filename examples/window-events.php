<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\Window;
use Sciter\WindowOptions;

$runtime = new Runtime();

$main = $runtime->createWindow(
    new WindowOptions(
        width: 600,
        height: 400,
        main: true
    )
);

$secondary = $runtime->createWindow(
    new WindowOptions(
        width: 400,
        height: 250,
        x: 750,
        y: 150,
        main: false
    )
);

$events = [];

$secondary->on('close', function (Window $window) use (&$events) {
    $events[] = 'first';

    echo "[Event] First close listener\n";
    echo "[Event] Closed: "
        . ($window->isClosed() ? 'YES' : 'NO')
        . PHP_EOL;
});

$removedId = $secondary->on('close', function () use (&$events) {
    $events[] = 'removed';

    echo "[Event] This should NOT execute\n";
});

$secondary->onClose(function (Window $window) use (&$events) {
    $events[] = 'onClose';

    echo "[Event] onClose compatibility listener\n";
});

// Remove the second listener before closing.
$secondary->off('close', $removedId);

$runtime->expose('windowEvents', [
    'closeSecondary' => function () use ($secondary): bool {
        $secondary->close();

        return $secondary->isClosing()
            || $secondary->isClosed();
    },

    'checkSecondary' => function () use ($secondary, &$events): array {
        return [
            'closed' => $secondary->isClosed(),
            'closing' => $secondary->isClosing(),
            'events' => $events,
        ];
    },
]);

$main->loadHtml(<<<'HTML'
<html>
<head>
    <title>Window Events Test</title>
    <style>
        body {
            font: system;
            padding: 24px;
        }

        button {
            display: block;
            margin: 10px 0;
            padding: 10px 16px;
        }

        #result {
            margin-top: 20px;
            padding: 12px;
            background: #edf2f7;
            color: #222;
        }
    </style>
</head>
<body>
    <h2>Window Events Test</h2>

    <p>Close the secondary window and check the events.</p>

    <button id="close">Close Secondary</button>
    <button id="check">Check Events</button>

    <pre id="result">Waiting...</pre>

    <script>
        const result = document.$('#result');

        document.$('#close').on('click', async function () {
            const requested =
                await globalThis.windowEvents.closeSecondary();

            result.textContent = requested
                ? 'Close requested'
                : 'No close request was needed';
        });

        document.$('#check').on('click', async function () {
            const state =
                await globalThis.windowEvents.checkSecondary();

            result.textContent =
                JSON.stringify(state, null, 2);
        });
    </script>
</body>
</html>
HTML);

$secondary->loadHtml(<<<'HTML'
<html>
<head>
    <title>Secondary Window</title>
    <style>
        body {
            font: system;
            padding: 24px;
        }
    </style>
</head>
<body>
    <h2>Secondary Window</h2>
    <p>Close this window to test lifecycle events.</p>
</body>
</html>
HTML);

$main->show();
$secondary->show();

$runtime->run();
