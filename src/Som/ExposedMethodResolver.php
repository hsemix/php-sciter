<?php

declare(strict_types=1);

namespace Sciter\Som;

use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use Sciter\Attributes\Expose;

final class ExposedMethodResolver
{
    /**
     * @return array<string, callable>
     */
    public function resolve(object $object): array
    {
        $reflection = new ReflectionClass($object);

        $methods = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Expose::class);

            if ($attributes === []) {
                continue;
            }

            if (!$method->isPublic() || $method->isStatic()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Exposed method %s::%s() must be public and non-static.',
                        $reflection->getName(),
                        $method->getName()
                    )
                );
            }

            $attribute = $attributes[0]->newInstance();

            $name = $attribute->name ?? $method->getName();

            if ($name === '') {
                throw new InvalidArgumentException(
                    'Exposed method name cannot be empty.'
                );
            }

            if (isset($methods[$name])) {
                throw new LogicException(
                    "Duplicate exposed method '{$name}'."
                );
            }

            $methods[$name] = $method->getClosure($object);
        }

        if ($methods === []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Class %s has no methods marked with #[Expose].',
                    $reflection->getName()
                )
            );
        }

        return $methods;
    }
}
