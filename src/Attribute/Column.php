<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Closure;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public string|Closure|null $converter = null,
    ) {}
}
