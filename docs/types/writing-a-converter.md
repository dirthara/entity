---
id: writing-a-converter
title: Writing a converter
sidebar_position: 2
description: The TypeConverter interface, what each method has to promise, and a worked example.
---

# Writing a converter

A converter is the only place a value changes shape between PHP and a column, so
a type the package does not handle needs one. A value object such as `Money` is
the common case: the package has no way to guess which columns it belongs in or
what shape it should take.

## The interface

```php
namespace Dirthara\Entity\Type;

interface TypeConverter
{
    /**
     * @return class-string|non-empty-string
     */
    public function type(): string;

    public function toDatabase(mixed $value): string|int|float|bool|null;

    public function fromDatabase(mixed $value): mixed;
}
```

| Method | Has to promise |
| --- | --- |
| `type()` | The registry key. Return the class name to have a property of that type resolve to it automatically, or a short name such as `'money'` to be named with `#[Column(converter: ...)]`. |
| `toDatabase()` | A single bindable scalar, or `null`. Never an array or object. |
| `fromDatabase()` | The property's type. Whatever the driver reported is the input, so accept every shape a driver might use. |

Both directions receive `mixed` and must refuse what they cannot handle by
throwing `TypeConversionException`. Whether a property accepts `null` is settled
before a converter is reached, as described under [null](converters.md#null), so
a converter is never relied on to enforce it. The built-in converters still pass
`null` through in both directions, which is worth copying: it makes a converter
safe to call on a value that may be absent.

:::caution
`toDatabase()` returning anything but a scalar will not type-check. The query
builder binds parameters, and a bound parameter can only be a scalar or `null`,
so a converter that wants to store a structure has to encode it — that is what
the `json` and `serialized` converters do.
:::

## A worked example

```php
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\TypeConverter;

final readonly class MoneyConverter implements TypeConverter
{
    public function type(): string
    {
        return Money::class;
    }

    public function toDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Money) {
            throw TypeConversionException::invalidValue(
                expected: Money::class,
                actual: $value,
            );
        }

        return $value->cents;
    }

    public function fromDatabase(mixed $value): ?Money
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw TypeConversionException::invalidColumnValue(
                expected: 'a whole number of cents',
                actual: $value,
            );
        }

        return new Money((int) $value);
    }
}
```

`fromDatabase()` accepts a string as well as an `int` because that is the sort
of thing drivers differ about: the same `BIGINT` comes back as an `int` from one
and as a numeric string from another.

Register it and the property maps with no attribute, because `type()` returns
the class name the property is typed with:

```php
$types = new TypeRegistry([new MoneyConverter()]);
```

```php
public Money $price;
```

Registering it makes it the default for `Money` everywhere. For a single
property, name the class on the mapping instead and skip the registry:

```php
#[Column(converter: MoneyConverter::class)]
public Money $price;
```

That builds it with `new`, so a converter meant to be used this way should take
no required constructor arguments. See
[a converter for one property](converters.md#a-converter-for-one-property).

## Which exception to throw

| Factory | For |
| --- | --- |
| `TypeConversionException::invalidValue(string $expected, mixed $actual)` | `toDatabase()` was handed something it cannot write. |
| `TypeConversionException::invalidColumnValue(string $expected, mixed $actual)` | `fromDatabase()` was handed a column value it cannot read. |
| `TypeConversionException::conversionFailed(string $type, mixed $value, Throwable $previous)` | Something underneath failed, such as a `JsonException`. |
| `TypeConversionException::unsupportedType(string $type)` | Only the registry throws this one. |

Each records the value in its context, so keep a secret out of a converted
property or out of your logs. See [Error handling](../error-handling.md).

## Testing one

A converter is a pure function in both directions, so it needs no database:

```php
$converter = new MoneyConverter();

self::assertSame(1250, $converter->toDatabase(new Money(1250)));
self::assertEquals(new Money(1250), $converter->fromDatabase('1250'));
```

What a real driver reports for a column is the part worth checking against a
server. The package's own conformance suite maps one column of every convertible
type and runs it against all four databases, which is where a driver that
answers with a string for a float shows up.
