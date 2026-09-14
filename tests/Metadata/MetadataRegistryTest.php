<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Country;

final class MetadataRegistryTest extends EntityTestCase
{
    #[Test]
    public function it_builds_metadata_once_and_answers_with_it_again(): void
    {
        $registry = $this->registry();

        $metadata = $registry->for(Article::class);

        self::assertSame(Article::class, $metadata->entity);
        self::assertSame($metadata, $registry->for(Article::class));
    }

    #[Test]
    public function it_reports_only_what_it_has_already_built(): void
    {
        $registry = $this->registry();

        self::assertFalse($registry->has(Article::class));

        $registry->for(Article::class);

        self::assertTrue($registry->has(Article::class));
        self::assertFalse($registry->has(Country::class));
    }

    #[Test]
    public function it_forgets_what_it_built(): void
    {
        $registry = $this->registry();

        $metadata = $registry->for(Article::class);
        $registry->clear();

        self::assertFalse($registry->has(Article::class));
        self::assertNotSame($metadata, $registry->for(Article::class));
    }
}
