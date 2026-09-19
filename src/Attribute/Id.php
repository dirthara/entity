<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Closure;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Id
{
    /**
     * @param string|Closure|null $converter A registry key, the class name of a
     *        converter to build for this property alone, or a closure answering
     *        one. `null` looks a converter up by the property's type.
     */
    public function __construct(
        public ?string $column = null,
        public string|Closure|null $converter = null,
    ) {}
}
