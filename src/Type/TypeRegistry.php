<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Exceptions\TypeConversionException;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

final class TypeRegistry
{
    /**
     * @var array<string, TypeConverter>
     */
    private array $converters = [];

    /**
     * @param iterable<TypeConverter> $converters
     */
    public function __construct(iterable $converters = [])
    {
        // We register our own converters first so that the user can override them.
        $this->register(new StringConverter());
        $this->register(new IntegerConverter());
        $this->register(new FloatConverter());
        $this->register(new BooleanConverter());
        $this->register(new SerializedArrayConverter());
        $this->register(new JsonArrayConverter());

        foreach ($converters as $converter) {
            $this->register($converter);
        }
    }

    public function register(TypeConverter $converter): void
    {
        $this->converters[$converter->type()] = $converter;
    }

    public function has(string $type): bool
    {
        return isset($this->converters[$type]);
    }

    /**
     * @throws TypeConversionException
     */
    public function get(string $type): TypeConverter
    {
        return $this->converters[$type] ?? throw TypeConversionException::unsupportedType($type);
    }
}
