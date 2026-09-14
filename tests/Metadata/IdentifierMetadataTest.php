<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final class IdentifierMetadataTest extends TestCase
{
    #[Test]
    public function it_reports_a_single_property_identifier(): void
    {
        $property = $this->property('id');
        $identifier = new IdentifierMetadata([$property]);

        self::assertTrue($identifier->isSingle());
        self::assertFalse($identifier->isComposite());
        self::assertSame($property, $identifier->single());
    }

    #[Test]
    public function it_reports_a_composite_identifier(): void
    {
        $identifier = new IdentifierMetadata([$this->property('teamId'), $this->property('userId')]);

        self::assertTrue($identifier->isComposite());
        self::assertFalse($identifier->isSingle());
    }

    #[Test]
    public function it_refuses_to_answer_with_one_property_when_the_identifier_is_composite(): void
    {
        $identifier = new IdentifierMetadata([$this->property('teamId'), $this->property('userId')]);

        try {
            $identifier->single();

            self::fail('Expected the composite identifier to be reported.');
        } catch (InvalidIdentifierException $exception) {
            self::assertStringContainsString('teamId, userId', $exception->getMessage());
            self::assertSame(['teamId', 'userId'], $exception->getContext()['properties']);
        }
    }

    private function property(string $name): PropertyMetadata
    {
        return new PropertyMetadata(
            property: $name,
            column: $name,
            propertyType: 'int',
            converter: new IntegerConverter(),
            nullable: false,
            identifier: true,
            generated: false,
        );
    }
}
