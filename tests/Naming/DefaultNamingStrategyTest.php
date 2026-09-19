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
     * @return iterable<string, array{string, string, string}>
     */
    public static function entityForeignKeys(): iterable
    {
        yield 'an identifier that says nothing about the entity' => ['Post', 'id', 'post_id'];
        yield 'studly caps' => ['SalesPerson', 'id', 'sales_person_id'];
        yield 'an identifier already prefixed with the entity' => ['Account', 'account_uuid', 'account_uuid'];
        yield 'an identifier named after the entity' => ['Country', 'country', 'country'];
        yield 'an identifier that merely starts with the same letters' => ['Post', 'postage', 'post_postage'];
    }

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

    #[Test]
    public function it_names_a_foreign_key_after_the_property_and_the_identifier_it_points_at(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('country_code', $naming->relationForeignKey('country', 'code'));
        self::assertSame('billing_address_id', $naming->relationForeignKey('billingAddress', 'id'));
        self::assertSame('owner_account_uuid', $naming->relationForeignKey('owner', 'account_uuid'));
    }

    #[Test]
    #[DataProvider('entityForeignKeys')]
    public function it_names_a_foreign_key_after_the_entity_it_points_back_at(
        string $entity,
        string $identifier,
        string $expected,
    ): void {
        self::assertSame($expected, new DefaultNamingStrategy()->entityForeignKey($entity, $identifier));
    }

    #[Test]
    public function it_names_a_join_table_the_same_from_either_side(): void
    {
        $naming = new DefaultNamingStrategy();

        self::assertSame('post_tag', $naming->joinTable('Post', 'Tag'));
        self::assertSame('post_tag', $naming->joinTable('Tag', 'Post'));
        self::assertSame('account_user_profile', $naming->joinTable('UserProfile', 'Account'));
    }

    #[Test]
    public function it_leaves_a_join_table_singular(): void
    {
        self::assertSame('person_team', new DefaultNamingStrategy()->joinTable('Person', 'Team'));
    }
}
