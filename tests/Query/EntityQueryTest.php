<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Query;

use Dirthara\Entity\EntityStore;
use Dirthara\Entity\Query\EntityQuery;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Profile;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Database\Query\Sql\OrderDirection;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\EntityDatabaseException;

use function iterator_to_array;

final class EntityQueryTest extends EntityTestCase
{
    #[Test]
    public function it_reads_rows_as_entities(): void
    {
        $entities = $this->seeded()->query()->get();

        self::assertInstanceOf(Collection::class, $entities);
        self::assertCount(3, $entities);
        self::assertContainsOnlyInstancesOf(Article::class, $entities);
        // The assertions above narrow `$entities` to the bare contract.
        // @mago-expect analysis:mixed-argument
        self::assertSame(
            ['First', 'Second', 'Third'],
            array_map(
                static fn(mixed $article): string => self::entity(Article::class, $article)->title,
                $entities->values(),
            ),
        );
    }

    #[Test]
    public function it_translates_a_property_to_its_column(): void
    {
        $article = $this->seeded()->query()->where('title', ComparisonOperator::Equal, 'Second')->first();

        self::assertInstanceOf(Article::class, $article);
        self::assertSame(2, $article->id);
    }

    #[Test]
    public function it_accepts_an_operator_as_a_string(): void
    {
        self::assertSame(2, $this->seeded()->query()->where('id', '>', 1)->count());
    }

    #[Test]
    public function it_converts_the_value_it_compares(): void
    {
        $store = $this->seeded();

        self::assertSame(2, $store->query()->where('published', ComparisonOperator::Equal, true)->count());
        self::assertSame(1, $store->query()->where('published', ComparisonOperator::Equal, false)->count());
    }

    #[Test]
    public function it_widens_a_query_with_or_where(): void
    {
        $titles = $this->titles(
            $this
                ->seeded()
                ->query()
                ->where('title', ComparisonOperator::Equal, 'First')
                ->orWhere('title', ComparisonOperator::Equal, 'Third'),
        );

        self::assertSame(['First', 'Third'], $titles);
    }

    #[Test]
    public function it_compares_against_null(): void
    {
        $store = $this->profiles();

        self::assertSame(1, $store->query()->whereNull('bio')->count());
        self::assertSame(1, $store->query()->whereNotNull('bio')->count());
        self::assertSame(1, $store->query()->where('bio', ComparisonOperator::Equal, null)->count());
    }

    #[Test]
    public function it_matches_a_set_of_values(): void
    {
        $store = $this->seeded();

        self::assertSame(['First', 'Third'], $this->titles($store->query()->whereIn('title', ['First', 'Third'])));
        self::assertSame(['Second'], $this->titles($store->query()->whereNotIn('title', ['First', 'Third'])));
    }

    #[Test]
    public function it_matches_a_range(): void
    {
        self::assertSame(['First', 'Second'], $this->titles($this->seeded()->query()->whereBetween('id', 1, 2)));
    }

    #[Test]
    public function it_groups_conditions(): void
    {
        $titles = $this->titles($this
            ->seeded()
            ->query()
            ->where('published', ComparisonOperator::Equal, true)
            ->whereNested(static function (EntityQuery $query): void {
                $query->where('title', ComparisonOperator::Equal, 'First')->orWhere(
                    'title',
                    ComparisonOperator::Equal,
                    'Second',
                );
            }));

        self::assertSame(['First', 'Second'], $titles);
    }

    #[Test]
    public function it_orders_by_a_property(): void
    {
        $store = $this->seeded();

        self::assertSame(['First', 'Second', 'Third'], $this->titles($store->query()->orderBy('title')));
        self::assertSame(['Third', 'Second', 'First'], $this->titles($store->query()->orderByDesc('title')));
        self::assertSame(
            ['Third', 'Second', 'First'],
            $this->titles($store->query()->orderBy('id', OrderDirection::Descending)),
        );
    }

    #[Test]
    public function it_limits_and_offsets(): void
    {
        self::assertSame(['Second'], $this->titles($this->seeded()->query()->orderBy('id')->limit(1)->offset(1)));
    }

    #[Test]
    public function it_answers_with_nothing_when_no_row_matches(): void
    {
        self::assertNull($this->seeded()->query()->where('title', ComparisonOperator::Equal, 'Missing')->first());
    }

    #[Test]
    public function it_streams_entities_one_row_at_a_time(): void
    {
        $entities = iterator_to_array($this->seeded()->query()->orderBy('id')->cursor());

        self::assertCount(3, $entities);
        self::assertContainsOnlyInstancesOf(Article::class, $entities);
    }

    #[Test]
    public function it_answers_whether_a_row_exists_and_how_many(): void
    {
        $store = $this->seeded();

        self::assertTrue($store->query()->where('title', ComparisonOperator::Equal, 'First')->exists());
        self::assertFalse($store->query()->where('title', ComparisonOperator::Equal, 'Missing')->exists());
        self::assertSame(3, $store->query()->count());
        self::assertSame(1, $store->query()->where('title', ComparisonOperator::Equal, 'First')->count());
    }

    /**
     * Every clause mutates the builder it holds and answers with the same query, so
     * a query is a builder rather than a value despite the `readonly` class.
     */
    #[Test]
    public function it_answers_with_itself_so_clauses_can_be_chained(): void
    {
        $query = $this->seeded()->query();

        self::assertSame($query, $query->where('id', ComparisonOperator::Equal, 1));
        self::assertSame($query, $query->whereNull('title'));
        self::assertSame($query, $query->whereNotNull('title'));
        self::assertSame($query, $query->whereIn('id', [1]));
        self::assertSame($query, $query->whereNotIn('id', [2]));
        self::assertSame($query, $query->whereBetween('id', 1, 2));
        self::assertSame($query, $query->orderBy('id'));
        self::assertSame($query, $query->limit(1));
        self::assertSame($query, $query->offset(0));
        self::assertSame($query, $query->whereNested(static fn(EntityQuery $nested): EntityQuery => $nested));
    }

    #[Test]
    public function it_reports_a_property_the_entity_does_not_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unknown property "missing"');

        $this->seeded()->query()->where('missing', ComparisonOperator::Equal, 1);
    }

    #[Test]
    public function it_reports_which_read_the_database_refused(): void
    {
        $store = $this->store(Article::class);

        foreach (['get', 'first', 'exists', 'count'] as $operation) {
            try {
                $store->query()->{$operation}();

                self::fail(sprintf('Expected %s() to be reported.', $operation));
            } catch (EntityDatabaseException $exception) {
                self::assertSame($operation, $exception->getContext()['operation']);
                self::assertSame(Article::class, $exception->getContext()['entity']);
            }
        }
    }

    #[Test]
    public function it_reports_a_stream_the_database_refused(): void
    {
        $this->expectException(EntityDatabaseException::class);
        $this->expectExceptionMessage('Database operation "cursor" failed');

        iterator_to_array($this->store(Article::class)->query()->cursor());
    }

    private function seeded(): EntityStore
    {
        $this->createArticles();
        $this->insertArticle('First');
        $this->insertArticle('Second');
        $this->insertDraft('Third');

        return $this->store(Article::class);
    }

    private function profiles(): EntityStore
    {
        $this->createProfiles();

        $this->connection->execute('INSERT INTO profiles (display_name, meta, role, bio) VALUES (?, ?, ?, ?), (?, ?, ?, ?)', [
            'Ada',
            '{"tier":"gold"}',
            Role::Admin->value,
            null,
            'Grace',
            '{}',
            Role::Member->value,
            'Rear admiral',
        ]);

        return $this->store(Profile::class);
    }

    /**
     * @return list<string>
     */
    private function titles(EntityQuery $query): array
    {
        return array_map(
            static fn(mixed $article): string => self::entity(Article::class, $article)->title,
            $query->get()->values(),
        );
    }
}
