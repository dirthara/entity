<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Country;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

#[CoversClass(MetadataRegistry::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Id::class)]
#[UsesClass(EntityMetadata::class)]
#[UsesClass(IdentifierMetadata::class)]
#[UsesClass(MetadataFactory::class)]
#[UsesClass(PropertyMetadata::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(RelationStateRegistry::class)]
#[UsesClass(BooleanConverter::class)]
#[UsesClass(DateTimeConverter::class)]
#[UsesClass(FloatConverter::class)]
#[UsesClass(IntegerConverter::class)]
#[UsesClass(JsonArrayConverter::class)]
#[UsesClass(SerializedArrayConverter::class)]
#[UsesClass(StringConverter::class)]
#[UsesClass(TypeRegistry::class)]
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
