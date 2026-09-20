<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Collection\ImmutableCollection;

final readonly class RelationCollection
{
    public static function accepts(string $type): bool
    {
        if ($type === 'array' || $type === 'iterable') {
            return true;
        }

        return is_a(ImmutableCollection::class, $type, allow_string: true);
    }
}
