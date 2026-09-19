<?php

declare(strict_types=1);

namespace Dirthara\Entity\Naming;

final readonly class NoNamingStrategy implements NamingStrategy
{
    public function table(string $entityShortName): string
    {
        return $entityShortName;
    }

    public function column(string $property): string
    {
        return $property;
    }
}
