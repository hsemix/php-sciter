<?php

namespace Sciter\Som;

use FFI;
use Sciter\Runtime;
use Sciter\Value;

final class MethodDispatcher
{
    private Value $value;

    public function __construct(Runtime $runtime)
    {
        $this->value = new Value($runtime);
    }

    public function dispatch(
        callable $callback,
        int $argc,
        $argv,
        $result
    ): bool {
        try {
            $arguments = [];

            for ($i = 0; $i < $argc; $i++) {
                $arguments[] = $this->value->toPhp(
                    FFI::addr($argv[$i])
                );
            }

            $returnValue = $callback(...$arguments);

            $this->value->fromPhp($result, $returnValue);

            return true;
        } catch (\Throwable $e) {
            error_log(
                '[Sciter SOM] ' . $e::class . ': ' . $e->getMessage()
            );

            return false;
        }
    }
}
