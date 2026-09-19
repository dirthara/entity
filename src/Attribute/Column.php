<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Closure;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    /**
     * @param string|array<string, string>|null $name
     */
    public function __construct(
        public string|array|null $name = null,
        public string|Closure|null $converter = null,
    ) {}
}
