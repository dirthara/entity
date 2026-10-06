<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use DateTimeImmutable;

use function substr;

enum TemporalFormat: string
{
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
    case Timestamp = 'timestamp';

    public const int MAX_FRACTIONAL_SECONDS = 6;

    /**
     * @return non-empty-string
     */
    public function format(): string
    {
        return match ($this) {
            self::Date => 'Y-m-d',
            self::Time => 'H:i:s',
            self::DateTime, self::Timestamp => 'Y-m-d H:i:s',
        };
    }

    public function keepsFractionalSeconds(): bool
    {
        return $this !== self::Date;
    }

    public function write(DateTimeImmutable $value, ?int $fractionalSeconds = null): string
    {
        $written = $value->format($this->format());

        if ($fractionalSeconds === null || $fractionalSeconds === 0 || !$this->keepsFractionalSeconds()) {
            return $written;
        }

        return $written . '.' . substr($value->format('u'), offset: 0, length: $fractionalSeconds);
    }

    public function hold(DateTimeImmutable $value, ?int $fractionalSeconds = null): DateTimeImmutable
    {
        $kept = $value->setTime(
            (int) $value->format('H'),
            (int) $value->format('i'),
            (int) $value->format('s'),
            $this->keptMicroseconds($value, $fractionalSeconds),
        );

        return match ($this) {
            self::Date => $kept->setTime(0, 0),
            self::Time => $kept->setDate(1970, 1, 1),
            self::DateTime, self::Timestamp => $kept,
        };
    }

    private function keptMicroseconds(DateTimeImmutable $value, ?int $fractionalSeconds): int
    {
        if ($fractionalSeconds === null || $fractionalSeconds === 0) {
            return 0;
        }

        return (
            (int) substr($value->format('u'), offset: 0, length: $fractionalSeconds)
            * (10 ** (self::MAX_FRACTIONAL_SECONDS - $fractionalSeconds))
        );
    }
}
