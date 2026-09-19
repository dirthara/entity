<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use DateTime;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\TemporalFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final class DateTimeConverterTest extends TestCase
{
    /**
     * @return iterable<string, array{TemporalFormat, string}>
     */
    public static function precisions(): iterable
    {
        yield 'date' => [TemporalFormat::Date, '2026-03-04'];
        yield 'time' => [TemporalFormat::Time, '10:15:30'];
        yield 'datetime' => [TemporalFormat::DateTime, '2026-03-04 10:15:30'];
        yield 'timestamp' => [TemporalFormat::Timestamp, '2026-03-04 10:15:30'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reported(): iterable
    {
        yield 'the format itself' => ['2026-03-04 10:15:30'];
        yield 'SQL Server milliseconds' => ['2026-03-04 10:15:30.000'];
        yield 'microseconds' => ['2026-03-04 10:15:30.123456'];
        yield 'a UTC offset' => ['2026-03-04 10:15:30+00'];
        yield 'ISO 8601' => ['2026-03-04T10:15:30Z'];
    }

    #[Test]
    #[DataProvider('precisions')]
    public function it_writes_each_precision(TemporalFormat $temporal, string $expected): void
    {
        $converter = new DateTimeConverter($temporal->value, $temporal);

        self::assertSame(
            $expected,
            $converter->toDatabase(new DateTimeImmutable('2026-03-04 10:15:30', new DateTimeZone('UTC'))),
        );
    }

    #[Test]
    #[DataProvider('precisions')]
    public function it_round_trips_each_precision(TemporalFormat $temporal, string $written): void
    {
        $converter = new DateTimeConverter($temporal->value, $temporal);

        self::assertSame($written, $converter->toDatabase($converter->fromDatabase($written)));
    }

    #[Test]
    public function it_converts_to_utc_before_writing(): void
    {
        $converter = new DateTimeConverter('datetime');

        $value = new DateTimeImmutable('2026-03-04 10:15:30', new DateTimeZone('Europe/Amsterdam'));

        self::assertSame('2026-03-04 09:15:30', $converter->toDatabase($value));
    }

    #[Test]
    public function it_does_not_mutate_the_value_it_writes(): void
    {
        $converter = new DateTimeConverter('datetime');

        $value = new DateTime('2026-03-04 10:15:30', new DateTimeZone('Europe/Amsterdam'));

        $converter->toDatabase($value);

        self::assertSame('Europe/Amsterdam', $value->getTimezone()->getName());
        self::assertSame('2026-03-04 10:15:30', $value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_reads_back_in_utc(): void
    {
        $value = new DateTimeConverter('datetime')->fromDatabase('2026-03-04 09:15:30');

        self::assertInstanceOf(DateTimeImmutable::class, $value);
        self::assertSame('UTC', $value->getTimezone()->getName());
        self::assertSame('2026-03-04 09:15:30', $value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_zeroes_what_the_precision_does_not_name(): void
    {
        $date = self::read(new DateTimeConverter('date', TemporalFormat::Date), '2026-03-04');
        $time = self::read(new DateTimeConverter('time', TemporalFormat::Time), '10:15:30');

        self::assertSame('2026-03-04 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertSame('1970-01-01 10:15:30', $time->format('Y-m-d H:i:s'));
    }

    #[Test]
    #[DataProvider('reported')]
    public function it_reads_what_a_driver_reports(string $reported): void
    {
        $value = self::read(new DateTimeConverter('datetime'), $reported);

        self::assertSame('2026-03-04 10:15:30', $value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_holds_a_reported_value_to_the_precision_of_its_column(): void
    {
        $date = self::read(new DateTimeConverter('date', TemporalFormat::Date), '2026-03-04 10:15:30');
        $time = self::read(new DateTimeConverter('time', TemporalFormat::Time), '2026-03-04 10:15:30');

        self::assertSame('2026-03-04 00:00:00', $date->format('Y-m-d H:i:s'));
        self::assertSame('1970-01-01 10:15:30', $time->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_drops_microseconds_a_driver_reports(): void
    {
        $value = self::read(
            new DateTimeConverter('timestamp', TemporalFormat::Timestamp),
            '2026-03-04 10:15:30.123456',
        );

        self::assertSame('000000', $value->format('u'));
    }

    #[Test]
    public function it_answers_a_mutable_date_time_when_asked(): void
    {
        $converter = new DateTimeConverter('datetime')->mutable();

        $value = $converter->fromDatabase('2026-03-04 10:15:30');

        self::assertInstanceOf(DateTime::class, $value);
        self::assertSame('2026-03-04 10:15:30', $value->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_stays_the_same_converter_when_it_is_already_mutable(): void
    {
        $converter = new DateTimeConverter('datetime')->mutable();

        self::assertSame($converter, $converter->mutable());
    }

    #[Test]
    public function it_keeps_its_key_when_it_turns_mutable(): void
    {
        self::assertSame('date', new DateTimeConverter('date', TemporalFormat::Date)->mutable()->type());
    }

    #[Test]
    public function it_writes_any_date_time_it_is_given(): void
    {
        $converter = new DateTimeConverter('datetime');

        $mutable = new DateTime('2026-03-04 10:15:30', new DateTimeZone('UTC'));

        self::assertSame('2026-03-04 10:15:30', $converter->toDatabase($mutable));
        self::assertInstanceOf(DateTimeInterface::class, $mutable);
    }

    #[Test]
    public function it_reports_a_value_that_is_not_a_date_time(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected "DateTimeInterface"');

        new DateTimeConverter('datetime')->toDatabase('2026-03-04 10:15:30');
    }

    #[Test]
    public function it_reports_a_column_value_that_is_not_a_string(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "int"');

        new DateTimeConverter('datetime')->fromDatabase(1_772_620_530);
    }

    #[Test]
    public function it_reports_a_column_value_it_cannot_read(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Conversion failed for type "datetime"');

        new DateTimeConverter('datetime')->fromDatabase('not a date at all');
    }

    private static function read(DateTimeConverter $converter, string $value): DateTimeInterface
    {
        $read = $converter->fromDatabase($value);

        self::assertInstanceOf(DateTimeInterface::class, $read);

        return $read;
    }
}
