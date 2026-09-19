<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\ColumnConverter;

final readonly class ConfiguredConverter implements ColumnConverter
{
    public function __construct(
        private string $prefix,
    ) {}

    public function type(): string
    {
        return 'configured';
    }

    public function toDatabase(mixed $value): string
    {
        return $this->prefix . (string) $value;
    }

    public function fromDatabase(mixed $value): string
    {
        return (string) $value;
    }
}
