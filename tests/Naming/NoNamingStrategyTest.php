<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Naming;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Naming\NoNamingStrategy;

final class NoNamingStrategyTest extends TestCase
{
    #[Test]
    public function it_leaves_a_table_name_as_the_class_is_written(): void
    {
        self::assertSame('BlogPost', new NoNamingStrategy()->table('BlogPost'));
    }

    #[Test]
    public function it_leaves_a_column_name_as_the_property_is_written(): void
    {
        self::assertSame('displayName', new NoNamingStrategy()->column('displayName'));
    }

    #[Test]
    public function it_joins_a_foreign_key_out_of_the_property_and_the_identifier(): void
    {
        $naming = new NoNamingStrategy();

        self::assertSame('country_code', $naming->relationForeignKey('country', 'code'));
        self::assertSame('billingAddress_id', $naming->relationForeignKey('billingAddress', 'id'));
    }

    #[Test]
    public function it_joins_a_foreign_key_out_of_the_entity_and_the_identifier(): void
    {
        $naming = new NoNamingStrategy();

        self::assertSame('BlogPost_id', $naming->entityForeignKey('BlogPost', 'id'));
        self::assertSame('Account_account_uuid', $naming->entityForeignKey('Account', 'account_uuid'));
    }

    #[Test]
    public function it_joins_a_join_table_out_of_both_entity_names_the_same_from_either_side(): void
    {
        $naming = new NoNamingStrategy();

        self::assertSame('Post_Tag', $naming->joinTable('Post', 'Tag'));
        self::assertSame('Post_Tag', $naming->joinTable('Tag', 'Post'));
    }
}
