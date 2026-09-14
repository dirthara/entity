<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Exception;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Exception\EntityException;
use Dirthara\Entity\Exception\PersistenceException;

final class EntityExceptionTest extends TestCase
{
    #[Test]
    public function it_carries_nothing_by_default(): void
    {
        $exception = new EntityException();

        self::assertSame('', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->getContext());
    }

    #[Test]
    public function it_keeps_its_cause_and_merges_what_is_added_to_its_context(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new PersistenceException('failed', 7, $previous, ['entity' => Article::class]);

        self::assertSame('failed', $exception->getMessage());
        self::assertSame(7, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame($exception, $exception->addContext(['operation' => 'insert', 'entity' => 'Other']));
        self::assertSame(['entity' => 'Other', 'operation' => 'insert'], $exception->getContext());
    }
}
