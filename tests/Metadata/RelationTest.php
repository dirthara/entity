<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Ignore;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Tag;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Post;
use Dirthara\Entity\Tests\Entities\Draft;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Person;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Account;
use Dirthara\Entity\Tests\Entities\Comment;
use Dirthara\Entity\Tests\Entities\Country;
use Dirthara\Entity\Tests\Entities\Profile;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Tests\Entities\Category;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Relation\RelationLoading;
use Dirthara\Entity\Tests\Entities\Membership;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Tests\Entities\Invalid\MappedRelation;
use Dirthara\Entity\Tests\Entities\Invalid\BuiltinRelation;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeHasOne;
use Dirthara\Entity\Tests\Entities\Invalid\UnrelatedTarget;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeHasMany;
use Dirthara\Entity\Tests\Entities\Invalid\MultipleRelations;
use Dirthara\Entity\Tests\Entities\Invalid\WithoutIdentifier;
use Dirthara\Entity\Tests\Entities\Invalid\CollidingRelations;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeManyTarget;
use Dirthara\Entity\Tests\Entities\Invalid\MissingHasManyTarget;
use Dirthara\Entity\Tests\Entities\Invalid\MissingRelationTarget;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeBelongsToMany;
use Dirthara\Entity\Tests\Entities\Invalid\CollidingRelationColumn;
use Dirthara\Entity\Tests\Entities\Invalid\CompositeRelationTarget;
use Dirthara\Entity\Tests\Entities\Invalid\SelfReferencingRelation;
use Dirthara\Entity\Tests\Entities\Invalid\TargetWithoutIdentifier;

final class RelationTest extends EntityTestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function rejected(): iterable
    {
        yield 'two relations on one property' => [
            MultipleRelations::class,
            'Property "country" in entity "' . MultipleRelations::class . '" has multiple relations',
        ];
        yield 'a relation the entity also maps' => [
            MappedRelation::class,
            'Conflicting attributes for property "country"',
        ];
        yield 'a relation to a built-in type' => [
            BuiltinRelation::class,
            'Invalid relation type "string" for property "country"',
        ];
        yield 'a target the property type does not allow' => [
            UnrelatedTarget::class,
            'Invalid relation target "' . Tag::class . '" for property "country"',
        ];
        yield 'a belongs to many target that does not exist' => [
            MissingRelationTarget::class,
            'Failed to create mapping for entity "Dirthara\Entity\Tests\Entities\Missing"',
        ];
        yield 'a has many target that does not exist' => [
            MissingHasManyTarget::class,
            'Failed to create mapping for entity "Dirthara\Entity\Tests\Entities\Missing"',
        ];
        yield 'a self reference that derives one key name twice' => [
            SelfReferencingRelation::class,
            'Relation "related" in entity "'
                . SelfReferencingRelation::class
                . '" maps both of its foreign keys to column "self_referencing_relation_id"',
        ];
        yield 'a target without an identifier' => [
            TargetWithoutIdentifier::class,
            'Missing identifier for entity "' . WithoutIdentifier::class . '"',
        ];
        yield 'a foreign key a column already takes' => [
            CollidingRelationColumn::class,
            'Duplicate column "country_code" in entity "'
                . CollidingRelationColumn::class
                . '" for properties "code" and "country"',
        ];
        yield 'a foreign key another relation already takes' => [
            CollidingRelations::class,
            'Duplicate column "owner_id" in entity "'
                . CollidingRelations::class
                . '" for properties "home" and "away"',
        ];
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function compositeSources(): iterable
    {
        yield 'a has one' => [CompositeHasOne::class, 'profile'];
        yield 'a has many' => [CompositeHasMany::class, 'comments'];
        yield 'a belongs to many' => [CompositeBelongsToMany::class, 'tags'];
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function compositeTargets(): iterable
    {
        yield 'a belongs to one' => [CompositeRelationTarget::class, 'membership'];
        yield 'a belongs to many' => [CompositeManyTarget::class, 'memberships'];
    }

    #[Test]
    public function it_maps_a_relation_for_every_relation_attribute(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame(['country', 'author', 'profile', 'comments', 'tags'], array_keys($metadata->relations));
        self::assertInstanceOf(BelongsToOneMetadata::class, $metadata->relation('country'));
        self::assertInstanceOf(HasOneMetadata::class, $metadata->relation('profile'));
        self::assertInstanceOf(HasManyMetadata::class, $metadata->relation('comments'));
        self::assertInstanceOf(BelongsToManyMetadata::class, $metadata->relation('tags'));
    }

    #[Test]
    public function it_maps_a_relation_property_as_a_relation_and_not_as_a_column(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame(['id', 'title'], array_keys($metadata->properties));
    }

    #[Test]
    public function it_reads_the_target_from_the_property_type(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame(Country::class, $metadata->relation('country')->target);
        self::assertSame(Account::class, $metadata->relation('author')->target);
        self::assertSame(Profile::class, $metadata->relation('profile')->target);
    }

    #[Test]
    public function it_takes_the_target_the_attribute_names(): void
    {
        $metadata = $this->metadata(Draft::class);

        self::assertSame(Person::class, $metadata->relation('owner')->target);
        self::assertSame(Person::class, $metadata->relation('keeper')->target);
    }

    #[Test]
    public function it_reads_the_target_of_a_to_many_relation_from_the_attribute(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame(Comment::class, $metadata->relation('comments')->target);
        self::assertSame(Tag::class, $metadata->relation('tags')->target);
    }

    #[Test]
    public function it_names_a_belongs_to_one_key_after_the_property_and_the_target_identifier(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame(
            'country_code',
            self::entity(BelongsToOneMetadata::class, $metadata->relation('country'))->foreignKey,
        );
        self::assertSame(
            'author_account_uuid',
            self::entity(BelongsToOneMetadata::class, $metadata->relation('author'))->foreignKey,
        );
    }

    #[Test]
    public function it_names_a_has_one_key_after_this_entity_and_its_identifier(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame('post_id', self::entity(HasOneMetadata::class, $metadata->relation('profile'))->foreignKey);
    }

    #[Test]
    public function it_names_a_has_many_key_after_this_entity_and_its_identifier(): void
    {
        $metadata = $this->metadata(Post::class);

        self::assertSame('post_id', self::entity(HasManyMetadata::class, $metadata->relation('comments'))->foreignKey);
    }

    #[Test]
    public function it_names_a_belongs_to_many_join_table_and_both_of_its_keys(): void
    {
        $tags = self::entity(BelongsToManyMetadata::class, $this->metadata(Post::class)->relation('tags'));

        self::assertSame('post_tag', $tags->table);
        self::assertSame('post_id', $tags->foreignKey);
        self::assertSame('tag_id', $tags->relatedForeignKey);
    }

    #[Test]
    public function it_takes_the_foreign_key_the_attribute_names(): void
    {
        $metadata = $this->metadata(Draft::class);

        self::assertSame('home', self::entity(BelongsToOneMetadata::class, $metadata->relation('country'))->foreignKey);
        self::assertSame('draft_key', self::entity(HasOneMetadata::class, $metadata->relation('profile'))->foreignKey);
        self::assertSame(
            'draft_key',
            self::entity(HasManyMetadata::class, $metadata->relation('comments'))->foreignKey,
        );
    }

    #[Test]
    public function it_takes_the_join_table_and_both_keys_the_attribute_names(): void
    {
        $labels = self::entity(BelongsToManyMetadata::class, $this->metadata(Draft::class)->relation('labels'));

        self::assertSame('draft_labels', $labels->table);
        self::assertSame('draft_key', $labels->foreignKey);
        self::assertSame('label_key', $labels->relatedForeignKey);
    }

    #[Test]
    public function it_keeps_a_relation_explicit_until_the_attribute_asks_for_eager_loading(): void
    {
        $post = $this->metadata(Post::class);
        $draft = $this->metadata(Draft::class);

        self::assertSame(RelationLoading::Explicit, $post->relation('country')->loading);
        self::assertSame(RelationLoading::Explicit, $post->relation('tags')->loading);
        self::assertSame(RelationLoading::Eager, $draft->relation('country')->loading);
        self::assertSame(RelationLoading::Eager, $draft->relation('profile')->loading);
        self::assertSame(RelationLoading::Eager, $draft->relation('comments')->loading);
        self::assertSame(RelationLoading::Eager, $draft->relation('labels')->loading);
    }

    #[Test]
    public function it_records_whether_a_to_one_relation_accepts_null(): void
    {
        $post = $this->metadata(Post::class);
        $draft = $this->metadata(Draft::class);

        self::assertFalse(self::entity(BelongsToOneMetadata::class, $post->relation('country'))->nullable);
        self::assertTrue(self::entity(BelongsToOneMetadata::class, $post->relation('author'))->nullable);
        self::assertTrue(self::entity(HasOneMetadata::class, $post->relation('profile'))->nullable);
        self::assertFalse(self::entity(HasOneMetadata::class, $draft->relation('profile'))->nullable);
    }

    #[Test]
    public function it_counts_only_a_belongs_to_one_key_as_a_column_of_this_entity(): void
    {
        $metadata = $this->metadata(Draft::class);

        self::assertSame('draft_key', self::entity(HasOneMetadata::class, $metadata->relation('profile'))->foreignKey);
        self::assertSame(
            'draft_key',
            self::entity(HasManyMetadata::class, $metadata->relation('comments'))->foreignKey,
        );
        self::assertSame(
            'draft_key',
            self::entity(BelongsToManyMetadata::class, $metadata->relation('labels'))->foreignKey,
        );
    }

    #[Test]
    public function it_names_every_attribute_that_conflicts_with_a_relation(): void
    {
        try {
            $this->metadata(MappedRelation::class);

            self::fail('Expected the mapping to fail.');
        } catch (MappingException $exception) {
            self::assertSame(
                [BelongsToOne::class, Id::class, Column::class, Generated::class, Ignore::class],
                $exception->getContext()['attributes'],
            );
        }
    }

    #[Test]
    #[DataProvider('compositeSources')]
    public function it_refuses_a_relation_on_a_composite_identifier(string $entity, string $relation): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Entity "%s" has a composite identifier and cannot own the relation "%s"',
            $entity,
            $relation,
        ));

        $this->metadata($entity);
    }

    #[Test]
    #[DataProvider('compositeTargets')]
    public function it_refuses_a_relation_to_a_composite_identifier(string $entity, string $relation): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "%s" in entity "%s" points at "%s", which has a composite identifier',
            $relation,
            $entity,
            Membership::class,
        ));

        $this->metadata($entity);
    }

    #[Test]
    public function it_maps_a_many_to_many_an_entity_points_at_itself(): void
    {
        $related = self::entity(BelongsToManyMetadata::class, $this->metadata(Category::class)->relation('related'));

        self::assertSame(Category::class, $related->target);
        self::assertSame('category_relations', $related->table);
        self::assertSame('category_id', $related->foreignKey);
        self::assertSame('related_category_id', $related->relatedForeignKey);
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_refuses_a_relation_it_cannot_map(string $entity, string $message): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($message);

        $this->metadata($entity);
    }
}
