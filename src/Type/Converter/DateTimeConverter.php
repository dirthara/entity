<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use DateTime;
use Exception;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Type\TemporalFormat;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class DateTimeConverter implements TypeConverter
{
    public function __construct(
        private string $type,
        private TemporalFormat $temporal = TemporalFormat::DateTime,
        private bool $mutable = false,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function mutable(): self
    {
        if ($this->mutable) {
            return $this;
        }

        return new self(type: $this->type, temporal: $this->temporal, mutable: true);
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): string
    {
        if (!$value instanceof DateTimeInterface) {
            throw TypeConversionException::invalidValue(expected: DateTimeInterface::class, actual: $value);
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(self::utc())
            ->format($this->temporal->format());
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): DateTimeInterface
    {
        if (!is_string($value)) {
            throw TypeConversionException::invalidColumnValue(expected: $this->temporal->format(), actual: $value);
        }

        $parsed = $this->parse($value);

        return $this->mutable ? DateTime::createFromImmutable($parsed) : $parsed;
    }

    /**
     * @throws TypeConversionException
     */
    private function parse(string $value): DateTimeImmutable
    {
        $format = $this->temporal->format();

        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, self::utc());

        if ($parsed !== false) {
            return $parsed;
        }

        try {
            $reported = new DateTimeImmutable($value, self::utc());
        } catch (Exception $exception) {
            throw TypeConversionException::conversionFailed(type: $this->type, value: $value, previous: $exception);
        }

        return $this->temporal->hold($reported->setTimezone(self::utc()));
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
