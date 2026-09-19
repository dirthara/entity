<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

interface TypeConverter
{
    /**
     * @return class-string|non-empty-string
     */
    public function type(): string;
}
