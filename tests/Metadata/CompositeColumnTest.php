<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Entities\Order;
use Dirthara\Entity\Tests\EntityTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Tests\Entities\Invalid\NamedColumnsEmpty;
use Dirthara\Entity\Tests\Entities\Invalid\GeneratedComposite;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeIdentifier;
use Dirthara\Entity\Tests\Entities\Invalid\RepeatedPartColumns;
use Dirthara\Entity\Tests\Entities\Invalid\NamedColumnsMismatch;
use Dirthara\Entity\Tests\Entities\Invalid\NamedColumnsOnSingle;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeCollidesWithColumn;

final class CompositeColumnTest extends EntityTestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function rejected(): iterable
    {
        yield 'an identifier' => [CompositeIdentifier::class, 'because it is an identifier'];
        yield 'a generated column' => [GeneratedComposite::class, 'because the database generates it'];
        yield 'named columns on a single column' => [
            NamedColumnsOnSingle::class,
            'because its converter answers a single column',
        ];
        yield 'named columns that miss a part' => [NamedColumnsMismatch::class, 'expected one for each of'];
        yield 'a part named with an empty column' => [NamedColumnsEmpty::class, 'expected one for each of'];
        yield 'parts that are not distinct' => [RepeatedPartColumns::class, 'not distinct and non-empty'];
        yield 'a column another property already maps' => [
            CompositeCollidesWithColumn::class,
            'Duplicate column "price_currency"',
        ];
    }

    #[Test]
    public function it_derives_a_column_for_every_part_of_the_property(): void
    {
        $price = $this->metadata(Order::class)->property('price');

        self::assertSame(['amount' => 'price_amount', 'currency' => 'price_currency'], $price->columns);
    }

    #[Test]
    public function it_takes_a_string_name_as_the_prefix_for_every_part(): void
    {
        $shipping = $this->metadata(Order::class)->property('shipping');

        self::assertSame(['amount' => 'cost_amount', 'currency' => 'cost_currency'], $shipping->columns);
    }

    #[Test]
    public function it_takes_a_column_named_for_each_part(): void
    {
        $tax = $this->metadata(Order::class)->property('tax');

        self::assertSame(['amount' => 'tax_cents', 'currency' => 'tax_ccy'], $tax->columns);
    }

    #[Test]
    public function it_reports_the_property_as_composite(): void
    {
        $metadata = $this->metadata(Order::class);

        self::assertTrue($metadata->property('price')->isComposite());
        self::assertFalse($metadata->property('price')->isSingle());
        self::assertInstanceOf(MoneyConverter::class, $metadata->property('price')->composite());
    }

    #[Test]
    public function it_keeps_a_single_column_property_single(): void
    {
        $id = $this->metadata(Order::class)->property('id');

        self::assertFalse($id->isComposite());
        self::assertTrue($id->isSingle());
        self::assertSame(['id' => 'id'], $id->columns);
        self::assertSame('id', $id->column());
    }

    #[Test]
    public function it_refuses_to_answer_one_column_for_a_composite_property(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps to more than one column (price_amount, price_currency)');

        $this->metadata(Order::class)->property('price')->column();
    }

    #[Test]
    public function it_refuses_to_answer_one_converter_for_a_composite_property(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps to more than one column');

        $this->metadata(Order::class)->property('price')->single();
    }

    #[Test]
    public function it_refuses_to_answer_a_composite_converter_for_a_single_property(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Property "id" maps to a single column');

        $this->metadata(Order::class)->property('id')->composite();
    }

    #[Test]
    public function it_refuses_to_read_a_composite_property_until_the_hydrator_handles_one(): void
    {
        $this->createTable('CREATE TABLE orders (id INTEGER PRIMARY KEY, price_amount INTEGER, price_currency TEXT)');
        $this->connection->execute('INSERT INTO orders (id, price_amount, price_currency) VALUES (?, ?, ?)', [
            1,
            1250,
            'EUR',
        ]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps to more than one column');

        $this->store(Order::class)->all();
    }

    #[Test]
    public function it_refuses_to_write_a_composite_property_until_the_persister_handles_one(): void
    {
        $this->createTable('CREATE TABLE orders (id INTEGER PRIMARY KEY, price_amount INTEGER, price_currency TEXT)');

        $order = new Order();
        $order->id = 1;
        $order->price = new Money(1250, 'EUR');
        $order->shipping = new Money(0, 'EUR');
        $order->tax = new Money(0, 'EUR');

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps to more than one column');

        $this->store(Order::class)->insert($order);
    }

    #[Test]
    public function it_refuses_to_query_a_composite_property_until_the_query_handles_one(): void
    {
        $this->createTable('CREATE TABLE orders (id INTEGER PRIMARY KEY, price_amount INTEGER, price_currency TEXT)');

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('maps to more than one column');

        $this->store(Order::class)->query()->where('price', ComparisonOperator::Equal, new Money(1, 'EUR'));
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_reports_a_mapping_it_cannot_make(string $entity, string $message): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($message);

        $this->metadata($entity);
    }
}
