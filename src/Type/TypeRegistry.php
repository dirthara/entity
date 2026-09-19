<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use BackedEnum;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

final class TypeRegistry
{
    /**
     * Property types that fall back to a built-in converter registered under
     * another name. An alias only applies when nothing is registered under the
     * type itself, so registering under `array` still replaces the default.
     *
     * @var array<string, string>
     */
    private const array ALIASES = [
        'array' => 'json',
    ];

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
        return (
            isset($this->converters[$type])
            || isset(self::ALIASES[$type])
            || is_subclass_of($type, BackedEnum::class)
        );
    }

    /**
     * @throws TypeConversionException
     */
    public function get(string $type): TypeConverter
    {
        if (!isset($this->converters[$type]) && isset(self::ALIASES[$type])) {
            $type = self::ALIASES[$type];
        }

        return $this->converters[$type] ??= $this->build($type);
    }

    /**
     * @throws TypeConversionException
     */
    public function resolve(string $propertyType, ?string $converterType = null): TypeConverter
    {
        return $this->get($converterType ?? $propertyType);
    }

    /**
     * @throws TypeConversionException
     */
    private function build(string $type): TypeConverter
    {
        if (!is_subclass_of($type, BackedEnum::class)) {
            throw TypeConversionException::unsupportedType($type);
        }

        return new BackedEnumConverter($type);
    }
}
