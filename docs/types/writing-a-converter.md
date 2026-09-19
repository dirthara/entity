---
id: writing-a-converter
title: Writing a converter
sidebar_position: 2
description: The TypeConverter interface, what each method has to promise, and a worked example.
---

# Writing a converter

A converter is the only place a value changes shape between PHP and a column, so
a type the package does not handle needs one. `DateTimeImmutable` is the common
case: there is no built-in converter for it, because the column type and format
it should use are an application's decision.

## The interface

```php
namespace Dirthara\Entity\Type;

interface TypeConverter
{
    /**
     * @return class-string|non-empty-string
     */
    public function type(): string;

    public function toDatabase(mixed $value): string|int|float|bool;

    public function fromDatabase(mixed $value): mixed;
}
```

| Method | Has to promise |
| --- | --- |
| `type()` | The registry key. Return the class name to have a property of that type resolve to it automatically, or a short name such as `'money'` to be named with `#[Column(converter: ...)]`. |
| `toDatabase()` | A single bindable scalar. Never `null`, and never an array or object. |
| `fromDatabase()` | The property's type. Whatever the driver reported is the input, so accept every shape a driver might use. |

Both directions receive `mixed` and must refuse what they cannot handle by
throwing `TypeConversionException`. Neither is ever given `null`; the package
handles that around them, as described under
[null](converters.md#null).

:::caution
`toDatabase()` returning anything but a scalar will not type-check. The query
builder binds parameters, and a bound parameter can only be a scalar or `null`,
so a converter that wants to store a structure has to encode it — that is what
the `json` and `serialized` converters do.
:::

## A worked example

```php
use DateTimeImmutable;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\TypeConverter;

final readonly class DateTimeConverter implements TypeConverter
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function type(): string
    {
        return DateTimeImmutable::class;
    }

    public function toDatabase(mixed $value): string
    {
        if (!$value instanceof DateTimeImmutable) {
            throw TypeConversionException::invalidValue(
                expected: DateTimeImmutable::class,
                actual: $value,
            );
        }

        return $value->format(self::FORMAT);
    }

    public function fromDatabase(mixed $value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw TypeConversionException::invalidColumnValue(
                expected: 'date-time string',
                actual: $value,
            );
        }

        return DateTimeImmutable::createFromFormat(self::FORMAT, $value)
            ?: throw TypeConversionException::invalidColumnValue(
                expected: self::FORMAT,
                actual: $value,
            );
    }
}
```

Register it and the property maps with no attribute, because `type()` returns
the class name the property is typed with:

```php
$types = new TypeRegistry([new DateTimeConverter()]);
```

```php
public DateTimeImmutable $createdAt;
```

Registering it makes it the default for `DateTimeImmutable` everywhere. For a
single property, name the class on the mapping instead and skip the registry:

```php
#[Column(converter: DateTimeConverter::class)]
public DateTimeImmutable $createdAt;
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
$converter = new DateTimeConverter();

self::assertSame(
    '2026-09-14 12:00:00',
    $converter->toDatabase(new DateTimeImmutable('2026-09-14 12:00:00')),
);
```

What a real driver reports for a column is the part worth checking against a
server. The package's own conformance suite maps one column of every convertible
type and runs it against all four databases, which is where a driver that
answers with a string for a float shows up.
