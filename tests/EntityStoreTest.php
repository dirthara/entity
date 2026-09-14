<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests;

use Dirthara\Entity\EntityStore;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Country;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Tests\Entities\Membership;
use Dirthara\Entity\Exception\InvalidEntityException;
use Dirthara\Entity\Exception\EntityNotFoundException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final class EntityStoreTest extends EntityTestCase
{
    #[Test]
    public function it_starts_a_new_query_each_time(): void
    {
        $store = $this->seeded();

        self::assertNotSame($store->query(), $store->query());
    }

    #[Test]
    public function it_finds_an_entity_by_its_identifier(): void
    {
        $article = $this->seeded()->find(2);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Second', $article->title);
    }

    #[Test]
    public function it_answers_with_nothing_when_the_identifier_matches_no_row(): void
    {
        self::assertNull($this->seeded()->find(404));
    }

    #[Test]
    public function it_insists_on_an_entity(): void
    {
        $store = $this->seeded();

        self::assertSame('First', self::entity(Article::class, $store->findOrFail(1))->title);

        try {
            $store->findOrFail(404);

            self::fail('Expected the missing entity to be reported.');
        } catch (EntityNotFoundException $exception) {
            self::assertStringContainsString('not found for identifier "404"', $exception->getMessage());
            self::assertSame(404, $exception->getContext()['identifier']);
            self::assertSame(Article::class, $exception->getContext()['type']);
        }
    }

    #[Test]
    public function it_finds_an_entity_by_a_composite_identifier(): void
    {
        $membership = self::entity(Membership::class, $this->memberships()->findOrFail(['teamId' => 1, 'userId' => 2]));

        self::assertSame('owner', $membership->role);
    }

    #[Test]
    public function it_reports_a_composite_identifier_that_matches_no_row(): void
    {
        try {
            $this->memberships()->findOrFail(['teamId' => 1, 'userId' => 404]);

            self::fail('Expected the missing entity to be reported.');
        } catch (EntityNotFoundException $exception) {
            self::assertStringContainsString('{"teamId":1,"userId":404}', $exception->getMessage());
            self::assertSame(['teamId' => 1, 'userId' => 404], $exception->getContext()['identifier']);
        }
    }

    #[Test]
    public function it_needs_every_property_of_a_composite_identifier(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Missing property "userId" in composite identifier');

        $this->memberships()->find(['teamId' => 1]);
    }

    #[Test]
    public function it_refuses_a_single_value_for_a_composite_identifier(): void
    {
        $this->expectException(InvalidIdentifierException::class);
        $this->expectExceptionMessage('Composite identifier expected');

        $this->memberships()->find(1);
    }

    #[Test]
    public function it_reads_every_row_as_an_entity(): void
    {
        $entities = $this->seeded()->all();

        self::assertInstanceOf(Collection::class, $entities);
        self::assertCount(3, $entities);
    }

    #[Test]
    public function it_counts_and_reports_whether_any_row_exists(): void
    {
        $store = $this->seeded();

        self::assertSame(3, $store->count());
        self::assertTrue($store->exists());

        $this->connection->execute('DELETE FROM articles');

        self::assertSame(0, $store->count());
        self::assertFalse($store->exists());
    }

    #[Test]
    public function it_inserts_updates_and_deletes_through_the_persister(): void
    {
        $this->createArticles();

        $store = $this->store(Article::class);

        $article = new Article();
        $article->title = 'First';
        $article->published = false;

        $store->insert($article);

        self::assertSame(1, $article->id);
        self::assertSame(1, $store->count());

        $article->title = 'Second';

        self::assertSame(1, $store->update($article));
        self::assertSame('Second', self::entity(Article::class, $store->findOrFail(1))->title);
        self::assertSame(1, $store->delete($article));
        self::assertSame(0, $store->count());
    }

    #[Test]
    public function it_refuses_an_entity_it_does_not_map(): void
    {
        $store = $this->seeded();
        $country = new Country();

        foreach (['insert', 'update', 'delete'] as $operation) {
            try {
                $store->{$operation}($country);

                self::fail(sprintf('Expected %s() to refuse the entity.', $operation));
            } catch (InvalidEntityException $exception) {
                self::assertStringContainsString('Invalid entity set', $exception->getMessage());
                self::assertSame(Article::class, $exception->getContext()['expected']);
                self::assertSame(Country::class, $exception->getContext()['actual']);
            }
        }
    }

    private function seeded(): EntityStore
    {
        $this->createArticles();
        $this->insertArticle('First');
        $this->insertArticle('Second');
        $this->insertDraft('Third');

        return $this->store(Article::class);
    }

    private function memberships(): EntityStore
    {
        $this->createMemberships();

        $this->connection->execute('INSERT INTO memberships (team_id, user_id, role) VALUES (1, 2, ?)', ['owner']);

        return $this->store(Membership::class);
    }
}
