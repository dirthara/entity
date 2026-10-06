<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests;

use Dirthara\Entity\EntityStore;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Relation\Relations;
use Dirthara\Entity\Relation\RelationTree;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Replica;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Relation\Read\RelatedRows;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Relation\Read\HasOneReader;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Relation\Read\HasManyReader;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Relation\DefaultRelationLoader;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Relation\Read\BelongsToOneReader;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Relation\Handle\RelationRefresher;
use Dirthara\Entity\Relation\Read\BelongsToManyReader;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;
use Dirthara\Entity\Tests\Entities\Invalid\WithoutIdentifier;

#[CoversClass(EntityManager::class)]
#[CoversClass(MappingException::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Id::class)]
#[UsesClass(EntityStore::class)]
#[UsesClass(EntityDatabaseException::class)]
#[UsesClass(ReflectionHydrator::class)]
#[UsesClass(EntityMetadata::class)]
#[UsesClass(IdentifierMetadata::class)]
#[UsesClass(MetadataFactory::class)]
#[UsesClass(MetadataRegistry::class)]
#[UsesClass(PropertyMetadata::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(ReflectionPersister::class)]
#[UsesClass(EntityQuery::class)]
#[UsesClass(DefaultRelationHandleFactory::class)]
#[UsesClass(DefaultRelationLoader::class)]
#[UsesClass(RelationRefresher::class)]
#[UsesClass(BelongsToManyReader::class)]
#[UsesClass(BelongsToOneReader::class)]
#[UsesClass(HasManyReader::class)]
#[UsesClass(HasOneReader::class)]
#[UsesClass(RelatedRows::class)]
#[UsesClass(RelationStateRegistry::class)]
#[UsesClass(RelationTree::class)]
#[UsesClass(Relations::class)]
#[UsesClass(BooleanConverter::class)]
#[UsesClass(DateTimeConverter::class)]
#[UsesClass(FloatConverter::class)]
#[UsesClass(IntegerConverter::class)]
#[UsesClass(JsonArrayConverter::class)]
#[UsesClass(SerializedArrayConverter::class)]
#[UsesClass(StringConverter::class)]
#[UsesClass(TypeRegistry::class)]
final class EntityManagerTest extends EntityTestCase
{
    #[Test]
    public function it_opens_a_store_on_the_default_connection(): void
    {
        $database = $this->database();
        $database->execute(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, published INTEGER)',
        );
        $database->execute('INSERT INTO articles (title, published) VALUES (?, ?)', ['First', 1]);

        $store = $this->manager($database)->of(Article::class);

        self::assertSame('First', self::entity(Article::class, $store->findOrFail(1))->title);
    }

    #[Test]
    public function it_opens_a_store_on_the_connection_the_entity_names(): void
    {
        $database = $this->database('default', 'replica');
        $database->execute('CREATE TABLE replicas (id INTEGER PRIMARY KEY)', connection: 'replica');
        $database->execute('INSERT INTO replicas (id) VALUES (1)', connection: 'replica');

        $store = $this->manager($database)->of(Replica::class);

        self::assertInstanceOf(Replica::class, $store->findOrFail(1));
    }

    #[Test]
    public function it_prefers_the_connection_the_caller_names(): void
    {
        $database = $this->database('default', 'replica');
        $database->execute(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT, published INTEGER)',
            connection: 'replica',
        );
        $database->execute('INSERT INTO articles (id, title, published) VALUES (1, ?, 1)', ['Replicated'], 'replica');

        $store = $this->manager($database)->of(Article::class, 'replica');

        self::assertSame('Replicated', self::entity(Article::class, $store->findOrFail(1))->title);
    }

    #[Test]
    public function it_reports_a_connection_it_cannot_open(): void
    {
        try {
            $this->manager($this->database())->of(Article::class, 'missing');

            self::fail('Expected the connection to be reported.');
        } catch (EntityDatabaseException $exception) {
            self::assertSame('connect', $exception->context['operation']);
            self::assertSame(Article::class, $exception->context['entity']);
            self::assertNotNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_an_entity_it_cannot_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessageIsOrContains('Missing identifier');

        $this->manager($this->database())->of(WithoutIdentifier::class);
    }
}
