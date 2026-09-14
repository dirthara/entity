<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

interface TypeConverter
{
    /**
     * @return class-string|non-empty-string
     */
    public function type(): string;

    public function toDatabase(mixed $value): string|int|float|bool;

    public function fromDatabase(mixed $value): mixed;
}
