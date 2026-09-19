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

    public function relationForeignKey(string $property, string $identifierColumn): string
    {
        return sprintf('%s_%s', $this->column($property), $this->column($identifierColumn));
    }

    public function entityForeignKey(string $entityShortName, string $identifierColumn): string
    {
        return sprintf('%s_%s', $this->table($entityShortName), $this->column($identifierColumn));
    }

    public function joinTable(string $entityShortName, string $relatedEntityShortName): string
    {
        $entities = [
            $this->table($entityShortName),
            $this->table($relatedEntityShortName),
        ];

        sort($entities);

        return implode('_', $entities);
    }
}
