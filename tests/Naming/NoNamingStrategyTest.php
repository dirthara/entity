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
}
