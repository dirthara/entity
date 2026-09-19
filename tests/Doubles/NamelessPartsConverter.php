<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\CompositeConverter;

/**
 * Names the same column twice, which cannot be mapped.
 */
final readonly class NamelessPartsConverter implements CompositeConverter
{
    public function type(): string
    {
        return 'nameless';
    }

    public function parts(): array
    {
        return ['same', 'same'];
    }

    public function toDatabase(mixed $value): array
    {
        return ['same' => null];
    }

    public function fromDatabase(array $values): mixed
    {
        return null;
    }
}
