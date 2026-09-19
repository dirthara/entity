<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use DateTime;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

final class TypeRegistry
{
    /**
     * @var array<string, string>
     */
    private const array ALIASES = [
        'array' => 'json',
        DateTimeInterface::class => DateTimeImmutable::class,
    ];

    /**
     * @var array<string, ColumnConverter>
     */
    private array $converters = [];

    /**
     * @param iterable<ColumnConverter> $converters
     */
    public function __construct(iterable $converters = [])
    {
        $this->register(new StringConverter());
        $this->register(new IntegerConverter());
        $this->register(new FloatConverter());
        $this->register(new BooleanConverter());
        $this->register(new SerializedArrayConverter());
        $this->register(new JsonArrayConverter());

        $this->register(new DateTimeConverter(DateTimeImmutable::class));
        $this->register(new DateTimeConverter(DateTime::class, mutable: true));

        foreach (TemporalFormat::cases() as $temporal) {
            $this->register(new DateTimeConverter($temporal->value, $temporal));
        }

        foreach ($converters as $converter) {
            $this->register($converter);
        }
    }

    public function register(ColumnConverter $converter): void
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
    public function get(string $type): ColumnConverter
    {
        if (!isset($this->converters[$type]) && isset(self::ALIASES[$type])) {
            $type = self::ALIASES[$type];
        }

        return $this->converters[$type] ??= $this->build($type);
    }

    /**
     * @throws TypeConversionException
     */
    public function resolve(string $propertyType, ?string $converterType = null): ColumnConverter
    {
        $converter = $this->get($converterType ?? $propertyType);

        if ($propertyType === DateTime::class && $converter instanceof DateTimeConverter) {
            return $converter->mutable();
        }

        return $converter;
    }

    /**
     * @throws TypeConversionException
     */
    private function build(string $type): ColumnConverter
    {
        if (!is_subclass_of($type, BackedEnum::class)) {
            throw TypeConversionException::unsupportedType($type);
        }

        return new BackedEnumConverter($type);
    }
}
