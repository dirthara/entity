<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Type\CompositeConverter;
use Dirthara\Entity\Exception\MappingException;

final readonly class PropertyMetadata
{
    /**
     * @param non-empty-array<string, string> $columns
     */
    public function __construct(
        public string $property,
        public array $columns,
        public string $propertyType,
        public TypeConverter $converter,
        public bool $nullable,
        public bool $identifier,
        public bool $generated,
    ) {}

    public function isComposite(): bool
    {
        return $this->converter instanceof CompositeConverter;
    }

    public function isSingle(): bool
    {
        return $this->converter instanceof ColumnConverter;
    }

    /**
     * @throws MappingException
     */
    public function column(): string
    {
        $this->assertSingle();

        return array_values($this->columns)[0];
    }

    /**
     * @throws MappingException
     */
    public function single(): ColumnConverter
    {
        $converter = $this->converter;

        if (!$converter instanceof ColumnConverter) {
            throw MappingException::propertyIsComposite(
                property: $this->property,
                columns: array_values($this->columns),
            );
        }

        return $converter;
    }

    /**
     * @throws MappingException
     */
    public function composite(): CompositeConverter
    {
        if (!$this->converter instanceof CompositeConverter) {
            throw MappingException::propertyIsNotComposite($this->property);
        }

        return $this->converter;
    }

    /**
     * @throws MappingException
     */
    private function assertSingle(): void
    {
        if ($this->converter instanceof CompositeConverter) {
            throw MappingException::propertyIsComposite(
                property: $this->property,
                columns: array_values($this->columns),
            );
        }
    }
}
