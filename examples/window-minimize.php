<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\WindowOptions;

$runtime = new Runtime();

$window = $runtime->createWindow(
    new WindowOptions(
        width: 650,
        height: 400
    )
);

$runtime->expose('windowControls', [
    'minimize' => function () use ($window): int {
        $window->minimize();
        return $window->state();
    },

    'maximize' => function () use ($window): int {
        $window->maximize();
        return $window->state();
    },

    'restore' => function () use ($window): int {
        $window->restore();
        return $window->state();
    },

    'state' => fn(): int => $window->state(),
]);

$html = <<<'HTML'
<html>
<head>
    <title>Sciter Window State Test</title>
    <style>
        body {
            font: system;
            padding: 24dip;
        }

        button {
            padding: 8dip 14dip;
            margin-right: 8dip;
        }
    </style>
</head>
<body>
    <h2>Window State Test</h2>

    <p>Current state: <strong id="state">Unknown</strong></p>

    <button id="minimize">Minimize</button>
    <button id="maximize">Maximize</button>
    <button id="restore">Restore</button>
    <button id="refresh">Refresh State</button>

    
    <script>
        const stateNames = {
            0: 'Closed',
            1: 'Hidden',
            2: 'Shown',
            3: 'Minimized',
            4: 'Maximized',
            5: 'Full Screen'
        };

        function updateState(state) {
            document.$('#state').textContent =
                stateNames[state] ?? String(state);

            console.log('Window state: ' + state);
        }

        async function executeAction(action) {
            try {
                const state = await globalThis.windowControls[action]();

                console.log(
                    'Action: ' + action + ', returned state: ' + state
                );

                updateState(state);
            } catch (error) {
                console.log(
                    'Window action failed (' + action + '): ' + String(error)
                );
            }
        }

        for (const action of ['minimize', 'maximize', 'restore']) {
            document.$('#' + action).on('click', function() {
                executeAction(action);
            });
        }

        document.$('#refresh').on('click', function() {
            executeAction('state');
        });

        document.on('ready', function() {
            executeAction('state');
        });
        // Run asynchronously without top-level await.
        // executeAction('state');
    </script>

</body>
</html>
HTML;

try {
    $window->loadHtml($html)->show();
    $runtime->run();
} finally {
    $runtime->shutdown();
}
