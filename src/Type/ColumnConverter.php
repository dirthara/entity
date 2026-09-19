<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

interface ColumnConverter extends TypeConverter
{
    public function toDatabase(mixed $value): string|int|float|bool|null;

    public function fromDatabase(mixed $value): mixed;
}
