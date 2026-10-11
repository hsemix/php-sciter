<?php

declare(strict_types=1);

namespace Sciter\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Expose
{
    public function __construct(
        public readonly ?string $name = null
    ) {}
}
