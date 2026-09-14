<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Hydration;

use Closure;
use TypeError;
use ReflectionProperty;
use ReflectionException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Country;
use Dirthara\Entity\Tests\Entities\Profile;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Tests\Entities\Measurement;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Tests\Doubles\FaultyConverter;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Type\Converter\IntegerConverter;

final class ReflectionHydratorTest extends EntityTestCase
{
    #[Test]
    public function it_creates_an_instance_without_running_the_constructor(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Article::class);

        $entity = $hydrator->newInstance($metadata);

        self::assertInstanceOf(Article::class, $entity);
        self::assertFalse(new ReflectionProperty(Article::class, 'title')->isInitialized($entity));
        self::assertNotSame($entity, $hydrator->newInstance($metadata));
    }

    #[Test]
    public function it_reports_a_class_it_cannot_instantiate(): void
    {
        $this->expectException(CreateEntityException::class);
        $this->expectExceptionMessage('Failed to create entity "Closure"');

        new ReflectionHydrator()->newInstance($this->metadataFor(Closure::class));
    }

    #[Test]
    public function it_reports_a_class_that_does_not_exist(): void
    {
        $this->expectException(CreateEntityException::class);
        $this->expectExceptionMessage('Failed to create entity');

        new ReflectionHydrator()->newInstance($this->metadataFor('Dirthara\Entity\Tests\Entities\Missing'));
    }

    #[Test]
    public function it_assigns_every_mapped_column_through_its_converter(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Profile::class);
        $entity = $hydrator->newInstance($metadata);

        $hydrator->hydrate($metadata, $entity, [
            'id' => '7',
            'display_name' => 'Ada',
            'meta' => '{"tier":"gold"}',
            'role' => 'admin',
            'bio' => null,
        ]);

        self::assertInstanceOf(Profile::class, $entity);
        self::assertSame(7, $entity->id);
        self::assertSame('Ada', $entity->displayName);
        self::assertSame(['tier' => 'gold'], $entity->meta);
        self::assertSame(Role::Admin, $entity->role);
        self::assertNull($entity->bio);
    }

    #[Test]
    public function it_reads_a_null_column_as_null_whatever_the_property_type(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Measurement::class);
        $entity = $hydrator->newInstance($metadata);

        $hydrator->hydrate($metadata, $entity, ['id' => 1, 'celsius' => null]);

        self::assertNull(self::entity(Measurement::class, $entity)->celsius);
    }

    #[Test]
    public function it_reports_a_null_column_for_a_property_that_cannot_hold_null(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Article::class);

        try {
            $hydrator->hydrate($metadata, $hydrator->newInstance($metadata), [
                'id' => 1,
                'title' => null,
                'published' => 1,
            ]);

            self::fail('Expected the hydration to fail.');
        } catch (HydrationException $exception) {
            self::assertStringContainsString('Column "title" is null', $exception->getMessage());
            self::assertStringContainsString('does not accept null', $exception->getMessage());
            self::assertSame('title', $exception->getContext()['property']);
        }
    }

    #[Test]
    public function it_hydrates_the_same_entity_twice(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Article::class);
        $entity = $hydrator->newInstance($metadata);

        $hydrator->hydrate($metadata, $entity, ['id' => 1, 'title' => 'First', 'published' => 1]);
        $hydrator->hydrate($metadata, $entity, ['id' => 2, 'title' => 'Second', 'published' => 0]);

        self::assertInstanceOf(Article::class, $entity);
        self::assertSame(2, $entity->id);
        self::assertSame('Second', $entity->title);
        self::assertFalse($entity->published);
    }

    #[Test]
    public function it_refuses_an_entity_of_another_type(): void
    {
        $hydrator = new ReflectionHydrator();

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Invalid entity type');

        $hydrator->hydrate($this->metadata(Article::class), new Country(), []);
    }

    #[Test]
    public function it_reports_a_column_the_row_does_not_contain(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadata(Article::class);

        try {
            $hydrator->hydrate($metadata, $hydrator->newInstance($metadata), ['id' => 1, 'title' => 'First']);

            self::fail('Expected the hydration to fail.');
        } catch (HydrationException $exception) {
            self::assertStringContainsString('Missing column "published"', $exception->getMessage());
            self::assertSame('published', $exception->getContext()['column']);
        }
    }

    #[Test]
    public function it_reports_a_property_the_entity_does_not_declare(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadataFor(Article::class, $this->property('missing', new StringConverter()));

        try {
            $hydrator->hydrate($metadata, new Article(), ['missing' => 'value']);

            self::fail('Expected the hydration to fail.');
        } catch (HydrationException $exception) {
            self::assertStringContainsString('Unknown property "missing"', $exception->getMessage());
            self::assertInstanceOf(ReflectionException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_value_the_property_refuses(): void
    {
        $hydrator = new ReflectionHydrator();
        $metadata = $this->metadataFor(Article::class, $this->property('title', new FaultyConverter()));

        try {
            $hydrator->hydrate($metadata, new Article(), ['title' => 'First']);

            self::fail('Expected the hydration to fail.');
        } catch (HydrationException $exception) {
            self::assertStringContainsString('Failed to hydrate property "title"', $exception->getMessage());
            self::assertInstanceOf(TypeError::class, $exception->getPrevious());
        }
    }

    /**
     * @param class-string $entity
     */
    private function metadataFor(string $entity, ?PropertyMetadata ...$properties): EntityMetadata
    {
        $identifier = $this->property('id', new IntegerConverter());
        $mapped = ['id' => $identifier];

        foreach (array_filter($properties) as $property) {
            $mapped[$property->property] = $property;
        }

        return new EntityMetadata(
            entity: $entity,
            table: 'entities',
            identifier: new IdentifierMetadata([$identifier]),
            properties: $properties === [] ? $mapped : array_slice($mapped, offset: 1),
        );
    }

    private function property(
        string $name,
        StringConverter|IntegerConverter|FaultyConverter $converter,
    ): PropertyMetadata {
        return new PropertyMetadata(
            property: $name,
            column: $name,
            propertyType: 'string',
            converter: $converter,
            nullable: false,
            identifier: $name === 'id',
            generated: false,
        );
    }
}
