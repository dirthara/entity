<?php

declare(strict_types=1);

namespace Dirthara\Entity\Naming;

interface NamingStrategy
{
    public function table(string $entityShortName): string;

    public function column(string $property): string;
}
