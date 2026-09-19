<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Type\Converter\IntegerConverter;

final class EntityMetadataTest extends TestCase
{
    #[Test]
    public function it_looks_a_property_up_by_name(): void
    {
        $identifier = $this->property('id');
        $metadata = new EntityMetadata(
            entity: Article::class,
            table: 'articles',
            identifier: new IdentifierMetadata([$identifier]),
            properties: ['id' => $identifier],
        );

        self::assertSame(Article::class, $metadata->entity);
        self::assertSame('articles', $metadata->table);
        self::assertNull($metadata->connection);
        self::assertSame($identifier, $metadata->property('id'));
    }

    #[Test]
    public function it_reports_a_property_it_does_not_map(): void
    {
        $metadata = new EntityMetadata(
            entity: Article::class,
            table: 'articles',
            identifier: new IdentifierMetadata([$this->property('id')]),
            properties: [],
        );

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unknown property "missing"');

        $metadata->property('missing');
    }

    private function property(string $name): PropertyMetadata
    {
        return new PropertyMetadata(
            property: $name,
            columns: [$name => $name],
            propertyType: 'int',
            converter: new IntegerConverter(),
            nullable: false,
            identifier: true,
            generated: false,
        );
    }
}
