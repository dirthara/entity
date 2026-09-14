<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Replica;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Tests\Entities\Invalid\WithoutIdentifier;

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
            self::assertSame('connect', $exception->getContext()['operation']);
            self::assertSame(Article::class, $exception->getContext()['entity']);
            self::assertNotNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_an_entity_it_cannot_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Missing identifier');

        $this->manager($this->database())->of(WithoutIdentifier::class);
    }
}
