<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type;

use DateTimeZone;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\TemporalFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TemporalFormat::class)]
final class TemporalFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{TemporalFormat, ?int, string}>
     */
    public static function written(): iterable
    {
        yield 'a date ignores fractional seconds' => [TemporalFormat::Date, 6, '2026-03-04'];
        yield 'a time without fractional seconds' => [TemporalFormat::Time, null, '10:15:30'];
        yield 'a time at milliseconds' => [TemporalFormat::Time, 3, '10:15:30.987'];
        yield 'a datetime at whole seconds' => [TemporalFormat::DateTime, 0, '2026-03-04 10:15:30'];
        yield 'a timestamp at microseconds' => [TemporalFormat::Timestamp, 6, '2026-03-04 10:15:30.987654'];
    }

    /**
     * @return iterable<string, array{TemporalFormat, ?int, string}>
     */
    public static function held(): iterable
    {
        yield 'a date at midnight' => [TemporalFormat::Date, null, '2026-03-04 00:00:00.000000'];
        yield 'a time on the epoch' => [TemporalFormat::Time, 3, '1970-01-01 10:15:30.987000'];
        yield 'a datetime at whole seconds' => [TemporalFormat::DateTime, 0, '2026-03-04 10:15:30.000000'];
        yield 'a datetime at tenths' => [TemporalFormat::DateTime, 1, '2026-03-04 10:15:30.900000'];
        yield 'a timestamp at microseconds' => [TemporalFormat::Timestamp, 6, '2026-03-04 10:15:30.987654'];
    }

    #[Test]
    #[DataProvider('written')]
    public function it_writes_the_fractional_seconds_it_is_asked_for(
        TemporalFormat $temporal,
        ?int $fractionalSeconds,
        string $expected,
    ): void {
        self::assertSame($expected, $temporal->write($this->instant(), $fractionalSeconds));
    }

    #[Test]
    #[DataProvider('held')]
    public function it_holds_a_value_to_its_precision(
        TemporalFormat $temporal,
        ?int $fractionalSeconds,
        string $expected,
    ): void {
        self::assertSame($expected, $temporal->hold($this->instant(), $fractionalSeconds)->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_keeps_fractional_seconds_on_everything_but_a_date(): void
    {
        self::assertFalse(TemporalFormat::Date->keepsFractionalSeconds());
        self::assertTrue(TemporalFormat::Time->keepsFractionalSeconds());
        self::assertTrue(TemporalFormat::DateTime->keepsFractionalSeconds());
        self::assertTrue(TemporalFormat::Timestamp->keepsFractionalSeconds());
    }

    private function instant(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-03-04 10:15:30.987654', new DateTimeZone('UTC'));
    }
}
