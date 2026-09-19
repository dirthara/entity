<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use Dirthara\Entity\Exception\TypeConversionException;

interface CompositeConverter extends TypeConverter
{
    /**
     * @return non-empty-list<string>
     */
    public function parts(): array;

    /**
     * @return array<string, string|int|float|bool|null>
     *
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): array;

    /**
     * @param array<string, mixed> $values
     *
     * @throws TypeConversionException
     */
    public function fromDatabase(array $values): mixed;
}
