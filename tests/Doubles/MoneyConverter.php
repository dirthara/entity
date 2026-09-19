<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\CompositeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class MoneyConverter implements CompositeConverter
{
    public function type(): string
    {
        return Money::class;
    }

    public function parts(): array
    {
        return ['amount', 'currency'];
    }

    public function toDatabase(mixed $value): array
    {
        if (!$value instanceof Money) {
            throw TypeConversionException::invalidValue(expected: Money::class, actual: $value);
        }

        return ['amount' => $value->amount, 'currency' => $value->currency];
    }

    public function fromDatabase(array $values): Money
    {
        return new Money(amount: (int) $values['amount'], currency: (string) $values['currency']);
    }
}
