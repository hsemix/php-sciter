<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Sciter\Attributes\Expose;
use Sciter\Som\ExposedMethodResolver;

final class AppController
{
    #[Expose]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Expose('sum')]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function internalOperation(): string
    {
        return 'This should not be exposed.';
    }
}

$resolver = new ExposedMethodResolver();

$methods = $resolver->resolve(new AppController());

assert(
    array_keys($methods) === ['greet', 'sum']
);

assert(
    $methods['greet']('Hamid') === 'Hello, Hamid!'
);

assert(
    $methods['sum'](10, 20) === 30
);

if (isset($methods['internalOperation'])) {
    throw new RuntimeException(
        'Internal method was unexpectedly exposed.'
    );
}

echo "Resolved methods: " . implode(', ', array_keys($methods)) . PHP_EOL;
echo "Greeting: " . $methods['greet']('Hamid') . PHP_EOL;
echo "Sum: " . $methods['sum'](10, 20) . PHP_EOL;
echo "Internal method: not exposed" . PHP_EOL;
echo "All resolver checks passed." . PHP_EOL;