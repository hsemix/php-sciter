<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\WindowOptions;
use Sciter\WindowStateObserver;

$runtime = new Runtime();

$main = $runtime->createWindow(
    new WindowOptions(
        width: 600,
        height: 450,
        x: 100,
        y: 100,
        main: true
    )
);

$secondary = $runtime->createWindow(
    new WindowOptions(
        width: 450,
        height: 300,
        x: 750,
        y: 150,
        main: false
    )
);

$observer = new WindowStateObserver(
    $secondary,
    function (
        $window,
        int $previous,
        int $current,
        ?string $event
    ): void {
        echo "[Observer] {$previous} -> {$current}";

        if ($event !== null) {
            echo " ({$event})";
        }

        echo PHP_EOL;
    }
);

// $runtime->expose('windowInspector', [
//     'state' => function () use ($secondary): int {
//         if ($secondary->isClosed()) {
//             return 0;
//         }

//         return $secondary->state();
//     },
// ]);

$runtime->expose('windowInspector', [
    'state' => function () use ($secondary, $observer): int {
        if ($secondary->isClosed()) {
            return 0;
        }

        $observer->check();

        return $secondary->state();
    },
]);

$main->loadHtml(<<<'HTML'
<html>
<head>
    <title>Window State Inspector</title>
    <style>
        body {
            font: system;
            padding: 24px;
        }

        button {
            padding: 10px 16px;
            margin-top: 12px;
        }

        #result {
            margin-top: 20px;
            padding: 16px;
            background: #edf2f7;
            color: #222;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <h2>Window State Inspector</h2>

    <p>
        Change the secondary window using its native
        macOS controls, then click Refresh State.
    </p>

    <button id="refresh">Refresh State</button>

    <div id="result">Waiting...</div>

    <script>
        const stateNames = {
            0: 'Closed',
            1: 'Hidden',
            2: 'Shown',
            3: 'Minimized',
            4: 'Maximized',
            5: 'Fullscreen'
        };

        let lastState = null;

        document.$('#refresh').on('click', async function () {
            try {
                const state = await globalThis.windowInspector.state();

                document.$('#result').textContent =
                    'Current state: ' +
                    (stateNames[state] ?? 'Unknown') +
                    ' (' + state + ')';

                if (lastState !== null && lastState !== state) {
                    console.log(
                        '[Inspector] State changed: ' +
                        stateNames[lastState] + ' -> ' +
                        stateNames[state]
                    );
                }

                lastState = state;
            } catch (error) {
                console.error(error);
            }
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
        Use the macOS yellow button to minimize
        this window, or the green button to maximize it.
    </p>
</body>
</html>
HTML);

$main->show();
$secondary->show();

$observer->initialize();
$main->testPostedCallback();

try {
    $runtime->run();
} finally {
    $runtime->shutdown();
}

