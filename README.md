# php-sciter

Native desktop apps in PHP, with an HTML/CSS/JS UI rendered by [Sciter](https://sciter.com).
No Electron, no Node, no browser: PHP talks to the Sciter engine directly through PHP FFI.

> **Status: early / experimental.** The API will change. Only macOS has been tested so far.
> Linux and Windows binaries are bundled but unverified.

## Requirements

- PHP 8.2 or newer
- The `ffi` extension enabled (`php -m | grep FFI`)

## Install

```bash
composer require hsemix/php-sciter
```

The Sciter binaries for macOS, Linux and Windows ship in `bin/` and are picked automatically
for the current platform. You can also point to your own build:

```php
$runtime = new Sciter\Runtime('/path/to/libsciter.dylib');
```

## Hello, window

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Sciter\Runtime;
use Sciter\WindowOptions;

$runtime = new Runtime();

$window = $runtime->createWindow(new WindowOptions(width: 700, height: 450));

$window->expose('app', [
    'greet' => fn(string $name): string => "Hello, {$name}!",
]);

$window->loadHtml(<<<'HTML'
<html>
<body style="font: system; padding: 24dip">
    <h2 id="out"></h2>
    <script>
        document.$("#out").text = app.greet("Sciter");
    </script>
</body>
</html>
HTML)->show();

try {
    $runtime->run();
} finally {
    $runtime->shutdown();
}
```

Run it with `php hello.php`. `run()` blocks until the last window closes.

## Calling PHP from the UI

Expose callables as a named object that JavaScript can call directly.

**From an array of callables**

```php
$window->expose('app', [
    'add' => fn(int $a, int $b): int => $a + $b,
]);
```

**From an object, using `#[Expose]`**

Only methods marked with the attribute are reachable from JavaScript.

```php
use Sciter\Attributes\Expose;

final class AppController
{
    #[Expose]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Expose('sum')]            // exposed to JS under another name
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function secret(): void {}   // not exposed
}

$window->expose('app', new AppController());
```

```js
app.greet("Hamid");   // "Hello, Hamid!"
app.sum(1, 2);        // 3
```

`$runtime->expose()` does the same thing when the object should be shared across windows.

## Data types

Values are converted by copy in both directions.

| PHP | JavaScript |
|---|---|
| `null` | `null` |
| `bool`, `int`, `float`, `string` | boolean, number, string |
| list array `[1, 2, 3]` | array |
| associative array `['a' => 1]` | object |
| object | object (see below) |

PHP objects are flattened to a plain JS object using `jsonSerialize()` if the class implements
`JsonSerializable`, otherwise by iterating it if it is `Traversable`, otherwise by its public
properties. Private and protected properties are never sent.

Because everything is copied, changing a received object in the UI does not change PHP state.
Send it back through an exposed method to apply the change.

Known limits:
- An empty PHP array `[]` arrives in JS as `null`.
- There are no live references to PHP objects from JavaScript.

See [`examples/shared-data.php`](examples/shared-data.php) for a full round trip.

## Windows

```php
$window = $runtime->createWindow(new WindowOptions(
    width: 900,
    height: 600,
    main: true,        // closing the main window ends the app
    resizable: true,
    titlebar: true,
    controls: true,
));

$window->show();
$window->hide();
$window->minimize();
$window->maximize();
$window->fullscreen();
$window->restore();
$window->state();      // 0 closed, 1 hidden, 2 shown, 3 minimized, 4 maximized, 5 fullscreen

$window->onClose(function (Sciter\Window $window): void {
    echo "closed\n";
});

$window->close();
```

## Examples

Run any file from the repository root:

```bash
php examples/shared-data.php
```

| Example | Shows |
|---|---|
| `expose.php`, `expose-object.php`, `expose-resolver.php` | Exposing arrays and `#[Expose]` objects |
| `shared-data.php` | Assoc arrays and objects crossing the boundary |
| `window-options.php`, `window-state.php`, `window-lifecycle.php` | Window options, state and close events |
| `multiple-windows.php`, `window-runtime.php` | More than one window |
| `som-lifecycle.php`, `native-som-probe.php` | SOM reference counting and shutdown |

## Platform support

| Platform | Status |
|---|---|
| macOS | Tested |
| Linux | Binary bundled, untested |
| Windows | Binary bundled, untested |

## Project layout

```
src/Runtime.php       Sciter init, event loop, shutdown, exposing PHP objects
src/Window.php        Native windows and their lifecycle
src/Value.php         PHP <-> Sciter VALUE conversion
src/Som/              SOM assets that make PHP callables visible to JS
src/FFI/              C definitions loaded through PHP FFI
tests/native/         C++ probe used to verify SOM behaviour
```

## Licenses

php-sciter is released under the [MIT license](LICENSE).

The Sciter binaries in `bin/` are © Terra Informatica Software, Inc. and are redistributed under the
BSD 3-Clause license, see [`bin/LICENSE-sciter.txt`](bin/LICENSE-sciter.txt).
