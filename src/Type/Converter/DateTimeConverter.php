<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use DateTime;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use DateMalformedStringException;
use Dirthara\Entity\Type\TemporalFormat;
use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

use function is_string;

final readonly class DateTimeConverter implements ColumnConverter
{
    /**
     * @throws TypeConversionException
     */
    public function __construct(
        private string $type,
        private TemporalFormat $temporal = TemporalFormat::DateTime,
        private bool $mutable = false,
        private ?int $fractionalSeconds = null,
    ) {
        if ($fractionalSeconds === null) {
            return;
        }

        if (!$temporal->keepsFractionalSeconds()) {
            throw TypeConversionException::fractionalSecondsNotKept(type: $type);
        }

        if ($fractionalSeconds < 0 || $fractionalSeconds > TemporalFormat::MAX_FRACTIONAL_SECONDS) {
            throw TypeConversionException::invalidFractionalSeconds(
                type: $type,
                fractionalSeconds: $fractionalSeconds,
                maximum: TemporalFormat::MAX_FRACTIONAL_SECONDS,
            );
        }
    }

    public function type(): string
    {
        return $this->type;
    }

    public function mutable(): self
    {
        if ($this->mutable) {
            return $this;
        }

        return new self(
            type: $this->type,
            temporal: $this->temporal,
            mutable: true,
            fractionalSeconds: $this->fractionalSeconds,
        );
    }

    /**
     * @throws TypeConversionException
     */
    public function withFractionalSeconds(int $fractionalSeconds): self
    {
        return new self(
            type: $this->type,
            temporal: $this->temporal,
            mutable: $this->mutable,
            fractionalSeconds: $fractionalSeconds,
        );
    }

    public function keepsFractionalSeconds(): bool
    {
        return $this->temporal->keepsFractionalSeconds();
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw TypeConversionException::invalidValue(expected: DateTimeInterface::class, actual: $value);
        }

        return $this->temporal->write(
            DateTimeImmutable::createFromInterface($value)->setTimezone(self::utc()),
            $this->fractionalSeconds,
        );
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): ?DateTimeInterface
    {
        if ($value === null) {
            return null;
        }

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

        $fractional = DateTimeImmutable::createFromFormat('!' . $format . '.u', $value, self::utc());

        if ($fractional !== false) {
            return $this->temporal->hold($fractional, $this->fractionalSeconds);
        }

        try {
            $reported = new DateTimeImmutable($value, self::utc());
        } catch (DateMalformedStringException $exception) {
            throw TypeConversionException::conversionFailed(type: $this->type, value: $value, previous: $exception);
        }

        return $this->temporal->hold($reported->setTimezone(self::utc()), $this->fractionalSeconds);
    }

    private static function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
