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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

#[CoversClass(TypeConversionException::class)]
#[CoversClass(DateTimeConverter::class)]
#[CoversClass(TemporalFormat::class)]
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

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function fractionalSeconds(): iterable
    {
        yield 'whole seconds' => [0, '2026-03-04 10:15:30'];
        yield 'tenths' => [1, '2026-03-04 10:15:30.9'];
        yield 'milliseconds' => [3, '2026-03-04 10:15:30.987'];
        yield 'microseconds' => [6, '2026-03-04 10:15:30.987654'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reportedFractions(): iterable
    {
        yield 'the digits it writes' => ['2026-03-04 10:15:30.123', '2026-03-04 10:15:30.123000'];
        yield 'trailing zeros dropped' => ['2026-03-04 10:15:30.1', '2026-03-04 10:15:30.100000'];
        yield 'no fraction at all' => ['2026-03-04 10:15:30', '2026-03-04 10:15:30.000000'];
        yield 'more digits than it keeps' => ['2026-03-04 10:15:30.123456', '2026-03-04 10:15:30.123000'];
        yield 'SQL Server seven digits' => ['2026-03-04 10:15:30.1239999', '2026-03-04 10:15:30.123000'];
    }

    #[Test]
    #[DataProvider('fractionalSeconds')]
    public function it_writes_the_fractional_seconds_it_keeps(int $fractionalSeconds, string $expected): void
    {
        $converter = new DateTimeConverter('datetime', fractionalSeconds: $fractionalSeconds);

        self::assertSame(
            $expected,
            $converter->toDatabase(new DateTimeImmutable('2026-03-04 10:15:30.987654', new DateTimeZone('UTC'))),
        );
    }

    #[Test]
    public function it_cuts_rather_than_rounds_the_digits_it_does_not_keep(): void
    {
        $converter = new DateTimeConverter('datetime', fractionalSeconds: 3);

        self::assertSame(
            '2026-03-04 10:15:59.999',
            $converter->toDatabase(new DateTimeImmutable('2026-03-04 10:15:59.999999', new DateTimeZone('UTC'))),
        );
    }

    #[Test]
    #[DataProvider('reportedFractions')]
    public function it_reads_back_the_fractional_seconds_it_keeps(string $reported, string $expected): void
    {
        $converter = new DateTimeConverter('datetime', fractionalSeconds: 3);

        self::assertSame($expected, $converter->fromDatabase($reported)?->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_keeps_fractional_seconds_on_a_time(): void
    {
        $converter = new DateTimeConverter('time', TemporalFormat::Time, fractionalSeconds: 3);

        self::assertSame('10:15:30.987', $converter->toDatabase(new DateTimeImmutable('2026-03-04 10:15:30.987654')));
        self::assertSame(
            '1970-01-01 10:15:30.987000',
            $converter->fromDatabase('10:15:30.987654')?->format('Y-m-d H:i:s.u'),
        );
    }

    #[Test]
    public function it_keeps_its_fractional_seconds_when_it_turns_mutable(): void
    {
        $converter = new DateTimeConverter('datetime', fractionalSeconds: 3)->mutable();

        $value = $converter->fromDatabase('2026-03-04 10:15:30.123456');

        self::assertInstanceOf(DateTime::class, $value);
        self::assertSame('2026-03-04 10:15:30.123000', $value->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_stays_mutable_when_it_is_given_fractional_seconds(): void
    {
        $converter = new DateTimeConverter('datetime', mutable: true)->withFractionalSeconds(6);

        $value = $converter->fromDatabase('2026-03-04 10:15:30.123456');

        self::assertInstanceOf(DateTime::class, $value);
        self::assertSame('2026-03-04 10:15:30.123456', $value->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_answers_whether_its_precision_keeps_fractional_seconds(): void
    {
        self::assertTrue(new DateTimeConverter('datetime')->keepsFractionalSeconds());
        self::assertFalse(new DateTimeConverter('date', TemporalFormat::Date)->keepsFractionalSeconds());
    }

    #[Test]
    public function it_refuses_fractional_seconds_on_a_date(): void
    {
        try {
            new DateTimeConverter('date', TemporalFormat::Date, fractionalSeconds: 0);

            self::fail('Expected the fractional seconds to be refused.');
        } catch (TypeConversionException $exception) {
            self::assertSame('Type "date" keeps no fractional seconds', $exception->getMessage());
        }
    }

    #[Test]
    public function it_refuses_more_fractional_seconds_than_a_microsecond(): void
    {
        try {
            new DateTimeConverter('datetime')->withFractionalSeconds(7);

            self::fail('Expected the fractional seconds to be refused.');
        } catch (TypeConversionException $exception) {
            self::assertSame(
                'Type "datetime" cannot keep 7 digits of fractional seconds, expected 0 to 6',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function it_refuses_negative_fractional_seconds(): void
    {
        try {
            new DateTimeConverter('datetime', fractionalSeconds: -1);

            self::fail('Expected the fractional seconds to be refused.');
        } catch (TypeConversionException $exception) {
            self::assertSame(['type' => 'datetime', 'fractionalSeconds' => -1, 'maximum' => 6], $exception->context);
        }
    }
}
