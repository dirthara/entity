<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Persistence;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Link;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Country;
use Dirthara\Entity\Tests\Entities\Profile;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Tests\Entities\Membership;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Tests\Doubles\NullIdentifierConnection;

final class ReflectionPersisterTest extends EntityTestCase
{
    #[Test]
    public function it_inserts_every_column_the_database_does_not_generate(): void
    {
        $this->createArticles();

        $article = $this->article('First', published: false);

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Article::class), $article);

        self::assertSame([['id' => 1, 'title' => 'First', 'published' => 0]], $this->rows('articles'));
        self::assertSame(1, $article->id);
    }

    #[Test]
    public function it_inserts_an_entity_whose_identifier_is_assigned(): void
    {
        $this->createCountries();

        $country = new Country();
        $country->code = 'NL';
        $country->name = 'Netherlands';

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Country::class), $country);

        self::assertSame([['code' => 'NL', 'name' => 'Netherlands']], $this->rows('countries'));
        self::assertSame('NL', $country->code);
    }

    #[Test]
    public function it_inserts_an_entity_with_a_composite_identifier(): void
    {
        $this->createMemberships();

        $membership = new Membership();
        $membership->teamId = 1;
        $membership->userId = 2;
        $membership->role = 'owner';

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Membership::class), $membership);

        self::assertSame([['team_id' => 1, 'user_id' => 2, 'role' => 'owner']], $this->rows('memberships'));
    }

    #[Test]
    public function it_reports_an_insert_that_produced_no_identifier(): void
    {
        $this->createArticles();

        $connection = new NullIdentifierConnection($this->connection);

        try {
            new ReflectionPersister()->insert(
                $this->connected($connection),
                $this->metadata(Article::class),
                $this->article('First'),
            );

            self::fail('Expected the insert to be reported.');
        } catch (PersistenceException $exception) {
            self::assertStringContainsString('returned no generated identifier', $exception->getMessage());
            self::assertSame('id', $exception->getContext()['property']);
        }
    }

    #[Test]
    public function it_reports_an_insert_the_database_refused(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to insert entity');

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Article::class), $this->article('First'));
    }

    #[Test]
    public function it_refuses_an_entity_of_another_type(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Invalid entity type');

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Article::class), new Country());
    }

    #[Test]
    public function it_reports_a_property_that_was_never_set(): void
    {
        $this->createArticles();

        $article = new Article();
        $article->title = 'First';

        try {
            new ReflectionPersister()->insert($this->connected(), $this->metadata(Article::class), $article);

            self::fail('Expected the insert to be reported.');
        } catch (PersistenceException $exception) {
            self::assertStringContainsString('"published" of entity', $exception->getMessage());
            self::assertStringContainsString('is not initialized', $exception->getMessage());
        }
    }

    #[Test]
    public function it_refuses_null_for_a_property_that_does_not_accept_it(): void
    {
        $profile = new Profile();
        $profile->bio = null;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Null value not allowed for property "bio"');

        new ReflectionPersister()->insert(
            $this->connected(),
            $this->onlyProperty(Profile::class, 'profiles', 'bio'),
            $profile,
        );
    }

    #[Test]
    public function it_writes_null_for_a_property_that_accepts_it(): void
    {
        $this->createProfiles();

        $profile = new Profile();
        $profile->displayName = 'Ada';
        $profile->meta = ['tier' => 'gold'];
        $profile->role = Role::Admin;
        $profile->bio = null;

        new ReflectionPersister()->insert($this->connected(), $this->metadata(Profile::class), $profile);

        self::assertSame(
            [['id' => 1, 'display_name' => 'Ada', 'meta' => '{"tier":"gold"}', 'role' => 'admin', 'bio' => null]],
            $this->rows('profiles'),
        );
    }

    #[Test]
    public function it_reports_a_property_the_entity_does_not_declare(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Unknown property "missing"');

        new ReflectionPersister()->insert(
            $this->connected(),
            $this->onlyProperty(Article::class, 'articles', 'missing'),
            new Article(),
        );
    }

    #[Test]
    public function it_updates_every_column_except_the_identifier_and_what_is_generated(): void
    {
        $this->createArticles();
        $this->insertArticle('First');

        $article = $this->article('Second', published: false);
        $article->id = 1;

        $affected = new ReflectionPersister()->update($this->connected(), $this->metadata(Article::class), $article);

        self::assertSame(1, $affected);
        self::assertSame([['id' => 1, 'title' => 'Second', 'published' => 0]], $this->rows('articles'));
    }

    #[Test]
    public function it_updates_nothing_when_the_entity_maps_only_its_identifier(): void
    {
        $this->createTable('CREATE TABLE links (from_id INTEGER NOT NULL, to_id INTEGER NOT NULL)');

        $link = new Link();
        $link->fromId = 1;
        $link->toId = 2;

        self::assertSame(0, new ReflectionPersister()->update($this->connected(), $this->metadata(Link::class), $link));
    }

    #[Test]
    public function it_reports_an_update_that_matched_more_than_one_row(): void
    {
        $this->createAmbiguousArticles();
        $this->connection->execute('INSERT INTO articles (id, title, published) VALUES (1, ?, 1), (1, ?, 1)', [
            'a',
            'b',
        ]);

        $article = $this->article('Second');
        $article->id = 1;

        try {
            new ReflectionPersister()->update($this->connected(), $this->metadata(Article::class), $article);

            self::fail('Expected the update to be reported.');
        } catch (PersistenceException $exception) {
            self::assertStringContainsString('Unexpected affected rows "2"', $exception->getMessage());
            self::assertSame('update', $exception->getContext()['operation']);
            self::assertSame(1, $exception->getContext()['expectedMaximum']);
        }
    }

    #[Test]
    public function it_reports_an_update_the_database_refused(): void
    {
        $article = $this->article('First');
        $article->id = 1;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to update entity');

        new ReflectionPersister()->update($this->connected(), $this->metadata(Article::class), $article);
    }

    #[Test]
    public function it_deletes_the_row_its_identifier_matches(): void
    {
        $this->createArticles();
        $this->insertArticle('First');

        $article = $this->article('First');
        $article->id = 1;

        self::assertSame(1, new ReflectionPersister()->delete(
            $this->connected(),
            $this->metadata(Article::class),
            $article,
        ));
        self::assertSame([], $this->rows('articles'));
    }

    #[Test]
    public function it_reports_that_nothing_matched_the_identifier(): void
    {
        $this->createArticles();

        $article = $this->article('First');
        $article->id = 404;

        self::assertSame(0, new ReflectionPersister()->delete(
            $this->connected(),
            $this->metadata(Article::class),
            $article,
        ));
    }

    #[Test]
    public function it_reports_a_delete_that_matched_more_than_one_row(): void
    {
        $this->createAmbiguousArticles();
        $this->connection->execute('INSERT INTO articles (id, title, published) VALUES (1, ?, 1), (1, ?, 1)', [
            'a',
            'b',
        ]);

        $article = $this->article('First');
        $article->id = 1;

        try {
            new ReflectionPersister()->delete($this->connected(), $this->metadata(Article::class), $article);

            self::fail('Expected the delete to be reported.');
        } catch (PersistenceException $exception) {
            self::assertStringContainsString('Unexpected affected rows "2"', $exception->getMessage());
            self::assertSame('delete', $exception->getContext()['operation']);
        }
    }

    #[Test]
    public function it_reports_a_delete_the_database_refused(): void
    {
        $article = $this->article('First');
        $article->id = 1;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('Failed to delete entity');

        new ReflectionPersister()->delete($this->connected(), $this->metadata(Article::class), $article);
    }

    private function article(string $title, bool $published = true): Article
    {
        $article = new Article();
        $article->title = $title;
        $article->published = $published;

        return $article;
    }

    /**
     * @param class-string $entity
     */
    private function onlyProperty(string $entity, string $table, string $property): EntityMetadata
    {
        $metadata = new PropertyMetadata(
            property: $property,
            columns: [$property => $property],
            propertyType: 'string',
            converter: new StringConverter(),
            nullable: false,
            identifier: true,
            generated: false,
        );

        return new EntityMetadata(
            entity: $entity,
            table: $table,
            identifier: new IdentifierMetadata([$metadata]),
            properties: [$property => $metadata],
        );
    }
}
