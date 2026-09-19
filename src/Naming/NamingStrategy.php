<?php

declare(strict_types=1);

namespace Dirthara\Entity\Naming;

interface NamingStrategy
{
    public function table(string $entityShortName): string;

    public function column(string $property): string;

    public function relationForeignKey(string $property, string $identifierColumn): string;

    public function entityForeignKey(string $entityShortName, string $identifierColumn): string;

    public function joinTable(string $entityShortName, string $relatedEntityShortName): string;
}
