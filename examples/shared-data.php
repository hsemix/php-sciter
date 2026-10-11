<?php

/*
 * Sharing associative arrays and objects between PHP and the UI.
 *
 *   PHP array (assoc)  <->  JS object   { key: value }
 *   PHP array (list)   <->  JS array    [ a, b, c ]
 *   PHP object         ->   JS object   (JsonSerializable, Traversable,
 *                                        otherwise public properties)
 *
 * Objects travel by value: JS receives a copy, so changing it in the UI
 * does not change PHP state until you send it back through a method call.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Application;
use Sciter\Som\Asset;

final class Address implements JsonSerializable
{
    public function __construct(
        public string $city,
        public string $country,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}

final class User
{
    /** @param string[] $roles */
    public function __construct(
        public int $id,
        public string $name,
        public bool $active,
        public array $roles,
        public Address $address,
    ) {}
}

$app = new Application();
$runtime = $app->runtime();

$runtime->initialize();

/*
 * In-memory "database" owned by PHP.
 */
$users = [
    1 => new User(1, 'Hamid', true, ['admin', 'cashier'], new Address('Kampala', 'Uganda')),
    2 => new User(2, 'Sarah', false, ['cashier'], new Address('Nairobi', 'Kenya')),
];

$php = new Asset($runtime, 'php');

$php
    // 1. Associative array -> JS object (nested maps and lists included).
    ->method('getSettings', fn(): array => [
        'theme' => 'dark',
        'fontSize' => 14,
        'beta' => false,
        'window' => ['width' => 900, 'height' => 600],
        'recent' => ['a.txt', 'b.txt'],
        'lastError' => null,
    ])

    // 2. PHP object -> JS object (public props, nested objects too).
    ->method('getUser', fn(int $id): ?User => $users[$id] ?? null)

    // 3. List of objects -> JS array of objects.
    ->method('getUsers', fn(): array => array_values($users))

    // 4. JS object -> PHP associative array.
    ->method('describe', function (array $payload): array {
        return [
            'keys' => array_keys($payload),
            'count' => count($payload),
            'nameUpper' => strtoupper((string) ($payload['name'] ?? '')),
            'firstTag' => $payload['tags'][0] ?? null,
            'city' => $payload['address']['city'] ?? null,
        ];
    })

    // 5. Full round trip: JS edits a copy, PHP validates and stores it.
    ->method('saveUser', function (array $data) use (&$users): array {
        $id = (int) ($data['id'] ?? 0);

        if (!isset($users[$id])) {
            return ['ok' => false, 'error' => "No user with id {$id}"];
        }

        $user = $users[$id];
        $user->name = (string) ($data['name'] ?? $user->name);
        $user->active = (bool) ($data['active'] ?? $user->active);
        $user->roles = array_values($data['roles'] ?? $user->roles);

        return ['ok' => true, 'user' => $user];
    })
    ->register();

$window = $app->window(width: 1000, height: 760);

$window->loadHtml(<<<'HTML'
<!doctype html>
<html>
<head>
    <title>Shared data</title>
    <style>
        html { background: #f5f5f5; color: #222; font-family: system; }
        body { margin: 0; padding: 32px; }
        h1 { margin: 0 0 16px; }
        .card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 16px; }
        h3 { margin: 0 0 8px; }
        pre { margin: 0; font-family: monospace; font-size: 13px; white-space: pre-wrap; }
        button { margin-right: 8px; }
    </style>
</head>
<body>
    <h1>PHP &harr; UI shared data</h1>

    <div class="card">
        <h3>1. getSettings() &mdash; assoc array</h3>
        <pre id="settings"></pre>
    </div>

    <div class="card">
        <h3>2/3. getUser(1), getUsers() &mdash; objects</h3>
        <pre id="users"></pre>
    </div>

    <div class="card">
        <h3>4. describe({...}) &mdash; JS object sent to PHP</h3>
        <pre id="describe"></pre>
    </div>

    <div class="card">
        <h3>5. saveUser() &mdash; round trip</h3>
        <button id="save">Rename user 2 and deactivate</button>
        <button id="missing">Save unknown user</button>
        <pre id="save-result"></pre>
    </div>

<script type="module">
const show = (id, value) =>
    document.$("#" + id).text = JSON.stringify(value, null, 2);

try {
    // 1. Assoc array arrives as a plain object.
    const settings = php.getSettings();
    show("settings", settings);
    console.log("theme:", settings.theme);
    console.log("window.width:", settings.window.width);
    console.log("recent[1]:", settings.recent[1]);
    console.log("lastError === null:", settings.lastError === null);

    // 2/3. PHP objects arrive as plain objects, nested objects included.
    const user = php.getUser(1);
    console.log("user.address.city:", user.address.city);
    console.log("user.roles[0]:", user.roles[0]);
    console.log("unknown user is null:", php.getUser(99) === null);

    show("users", { one: user, all: php.getUsers() });

    // 4. A JS object becomes an associative array in PHP.
    show("describe", php.describe({
        name: "hamid",
        tags: ["x", "y"],
        address: { city: "Kampala" },
    }));

    // 5. Edit a copy in JS, send it back, PHP returns the stored state.
    document.$("#save").on("click", () => {
        const copy = php.getUser(2);
        copy.name = "Sarah (renamed)";
        copy.active = false;
        copy.roles.push("auditor");
        show("save-result", php.saveUser(copy));
    });

    document.$("#missing").on("click", () => {
        show("save-result", php.saveUser({ id: 99, name: "ghost" }));
    });
} catch (error) {
    console.error(error);
    document.$("#settings").text = String(error);
}
</script>
</body>
</html>
HTML);

try {
    $app->run($window);
} finally {
    $php->unregister();
    $runtime->shutdown();
}
