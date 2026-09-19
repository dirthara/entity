<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type;

use DateTimeImmutable;

enum TemporalFormat: string
{
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
    case Timestamp = 'timestamp';

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

    public function hold(DateTimeImmutable $value): DateTimeImmutable
    {
        $whole = $value->setTime((int) $value->format('H'), (int) $value->format('i'), (int) $value->format('s'));

        return match ($this) {
            self::Date => $whole->setTime(0, 0),
            self::Time => $whole->setDate(1970, 1, 1),
            self::DateTime, self::Timestamp => $whole,
        };
    }
}
