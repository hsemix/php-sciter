<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\WindowOptions;

$runtime = new Runtime();

// Main window
$main = $runtime->createWindow(
    new WindowOptions(
        width: 650,
        height: 450,
        x: 100,
        y: 100,
        main: true
    )
);

// Secondary window
$secondary = $runtime->createWindow(
    new WindowOptions(
        width: 450,
        height: 300,
        x: 800,
        y: 150,
        main: false
    )
);

// Expose lifecycle operations to JavaScript
$runtime->expose('windowLifecycle', [
    'isClosed' => function () use ($secondary): bool {
        $closed = $secondary->isClosed();

        echo '[PHP] Secondary window closed: '
            . ($closed ? 'YES' : 'NO')
            . PHP_EOL;

        return $closed;
    },

    'showClosedWindow' => function () use ($secondary): string {
        try {
            $secondary->show();

            return 'Unexpected: closed window was shown.';
        } catch (\LogicException $exception) {
            echo '[PHP] Expected exception: '
                . $exception->getMessage()
                . PHP_EOL;

            return $exception->getMessage();
        }
    },
    'closeSecondary' => function () use ($secondary): array {
        $secondary->close();

        return [
            'closing' => $secondary->isClosing(),
            'closed' => $secondary->isClosed(),
        ];
    },
]);

$main->loadHtml(<<<'HTML'
<html>
<head>
    <title>Window Lifecycle Test</title>
    <style>
        body {
            font: system;
            padding: 24px;
        }

        button {
            padding: 10px 16px;
            margin: 6px 0;
            display: block;
        }

        #result {
            margin-top: 20px;
            padding: 12px;
            background: #edf2f7;
            color: #222;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <h2>Window Lifecycle Test</h2>

    <p>
        Close the secondary window using its red
        macOS close button.
    </p>

    <button id="check">Check Secondary Window</button>
    <button id="show">Try Showing Closed Window</button>
    <button id="close">Close Secondary Window</button>

    <div id="result">Waiting for action...</div>

    <script>
        const result = document.$('#result');

        document.$('#check').on('click', async function () {
            const closed = await globalThis.windowLifecycle.isClosed();

            result.textContent = closed
                ? 'Secondary window is CLOSED'
                : 'Secondary window is still OPEN';
        });

        document.$('#show').on('click', async function () {
            const message =
                await globalThis.windowLifecycle.showClosedWindow();

            result.textContent = message;
        });

        document.$('#close').on('click', async function () {
            const closed = await globalThis.windowLifecycle.closeSecondary();

            result.textContent = closed
                ? 'Secondary window closed successfully'
                : 'Close requested; awaiting native destruction';
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

    <p>
        Close this window using the red macOS
        close button.
    </p>

    <p>
        Then return to the main window and
        check its lifecycle state.
    </p>
</body>
</html>
HTML);

$main->show();
$secondary->show();

$secondary->onClose(function ($window): void {
    echo '[PHP] Secondary window onClose fired' . PHP_EOL;
    echo '[PHP] isClosed: '
        . ($window->isClosed() ? 'YES' : 'NO')
        . PHP_EOL;
});

try {
    $runtime->run();
} finally {
    $runtime->shutdown();
}

echo '[PHP] Runtime finished' . PHP_EOL;
