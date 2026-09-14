<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Naming;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Naming\DefaultNamingStrategy;

final class DefaultNamingStrategyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tables(): iterable
    {
        yield 'a plain name' => ['Article', 'articles'];
        yield 'studly caps' => ['UserProfile', 'user_profiles'];
        yield 'an acronym' => ['HTTPRequest', 'http_requests'];
        yield 'digits' => ['Oauth2Client', 'oauth2_clients'];
        yield 'a consonant before y' => ['Category', 'categories'];
        yield 'a vowel before y' => ['Day', 'days'];
        yield 'ending in s' => ['Status', 'statuses'];
        yield 'ending in x' => ['Box', 'boxes'];
        yield 'ending in ch' => ['Church', 'churches'];
        yield 'ending in sh' => ['Dish', 'dishes'];
        yield 'an irregular plural' => ['Person', 'people'];
        yield 'an irregular plural as a suffix' => ['SalesPerson', 'sales_people'];
        yield 'an unchanged plural' => ['Series', 'series'];
        yield 'a latin plural' => ['Analysis', 'analyses'];
    }

    #[Test]
    #[DataProvider('tables')]
    public function it_derives_a_table_name_from_the_entity_name(string $entity, string $expected): void
    {
        self::assertSame($expected, new DefaultNamingStrategy()->table($entity));
    }

    #[Test]
    public function it_derives_a_column_name_from_the_property_name(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('id', $naming->column('id'));
        self::assertSame('display_name', $naming->column('displayName'));
        self::assertSame('http_status', $naming->column('HTTPStatus'));
    }
}
