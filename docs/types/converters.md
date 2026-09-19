---
id: converters
title: Converters
sidebar_position: 1
description: The built-in converters, how one is chosen, dates and times, nullable columns, and mapping a backed enum.
---

# Converters

Every mapped property has a converter. It turns the PHP value into something the
database can bind, and turns what the driver reports back into the property's
type. That is what makes a `bool` survive SQLite reporting `1` and PostgreSQL
reporting `true`.

## What ships

| Key | Property type | Writes | Reads |
| --- | --- | --- | --- |
| `string` | `string` | the string | casts whatever the driver reports to `string` |
| `int` | `int` | the integer | an `int`, or a numeric string |
| `float` | `float` | the float, or a numeric string | a `float`, or a numeric string |
| `bool` | `bool` | the boolean | `true`, `1`, `'1'`, `false`, `0` or `'0'` |
| `json` | `array` | `json_encode` | `json_decode` to an array |
| `serialized` | `array` | `serialize` | `unserialize`, with classes disallowed |
| `date` | a date and time | `Y-m-d` | that day at midnight |
| `time` | a date and time | `H:i:s` | that time on 1970-01-01 |
| `datetime` | a date and time | `Y-m-d H:i:s` | that instant |
| `timestamp` | a date and time | `Y-m-d H:i:s` | that instant |

The read side is lenient because drivers differ about what they report for the
same column. The write side is strict, because the property's own type already
guarantees what it holds; `float` is the one exception, taking a numeric string
as well. A value a converter cannot make sense of throws
`TypeConversionException` rather than being coerced.

A bare `array` property uses `json`. Nothing has to be named for it, and
`#[Column(converter: 'serialized')]` is how you ask for the other one.

:::note
The default is an alias, not a registration: `array` resolves to whatever is
registered under `json`, so replacing the `json` converter moves every bare
`array` with it. To point the default somewhere else entirely, register a
converter whose own `type()` returns `array`; anything registered under that key
wins over the alias.
:::

The `serialized` converter passes `allowed_classes: false`, so a payload can
only ever come back as arrays and scalars. Unserializing objects from a column
is how a database row turns into code execution, and no option is offered to
turn it on.

## How one is chosen

1. The `converter` option on `#[Id]` or `#[Column]`, if given:
   - a **closure** is called once and has to answer a converter;
   - a **class name** implementing `ColumnConverter` is built for this property
     alone;
   - anything else is a **registry key**.
2. Otherwise the property's type name: `string`, `int`, `float`, `bool`, or a
   class name.

Only the last of those three reaches the registry, so a converter named on a
property never has to be registered. What is registered stays the default for
the type it is registered under, which is what every property that names nothing
gets.

The name is looked up in the `TypeRegistry`. With nothing registered under it,
the registry tries two more things: `array` falls back to the `json` converter,
and a name that is a backed enum gets a converter built for it. Anything else
throws `TypeConversionException` naming the type, which is what an
`SplFileInfo` property does out of the box.

```php
public array $meta;     // 'array', so the 'json' converter

#[Column(converter: 'serialized')]
public array $legacy;   // a registry key

public bool $published; // looked up as 'bool'
```

## A converter for one property

A property can name a converter the registry has never heard of. Give it a class
name and the package builds it, or a closure and the package asks it:

```php
#[Column(converter: MoneyConverter::class)]
public Money $price;

#[Column(converter: Money::converter(...))]
public Money $total;
```

A class name is built with `new`, so the class has to be instantiable and its
constructor has to take no required arguments. A converter that needs arguments
is what the closure is for:

```php
final class Money
{
    public static function converter(): ColumnConverter
    {
        return new MoneyConverter(currency: 'EUR');
    }
}
```

Both are built once, while the entity is mapped, and belong to that property
alone: two properties naming the same class get two converters, and nothing is
added to the registry.

:::caution
The closure has to be written with first-class callable syntax,
`Money::converter(...)`. A literal closure such as `fn() => new MoneyConverter()`
is not a constant expression, and PHP rejects it in an attribute before any of
this package's code runs.
:::

| What the option names | Result |
| --- | --- |
| A registry key | The registered converter. |
| A converter class with no required constructor arguments | Built for this property. |
| A converter class that cannot be instantiated, or needs arguments | `MappingException` naming the property. |
| A closure answering a converter | That converter, for this property. |
| A closure answering anything else | `MappingException` naming the property. |
| A name that is none of these | `TypeConversionException`: unsupported type. |

## Dates and times

`DateTimeInterface`, `DateTimeImmutable` and `DateTime` properties map with no
attribute. Which class comes back is the property's own decision:

```php
public DateTimeInterface $createdAt;  // a DateTimeImmutable
public DateTimeImmutable $publishedAt; // a DateTimeImmutable
public DateTime $updatedAt;            // a DateTime
```

A property alone does not say how much of an instant the column holds, so the
precision is named when it is not a full date and time:

| Key | Writes | Column type it is meant for |
| --- | --- | --- |
| `date` | `Y-m-d` | `DATE` |
| `time` | `H:i:s` | `TIME` |
| `datetime` | `Y-m-d H:i:s` | `DATETIME`, `DATETIME2` |
| `timestamp` | `Y-m-d H:i:s` | `TIMESTAMP` |

```php
#[Column(converter: 'date')]
public DateTimeImmutable $bornOn;

#[Column(converter: 'time')]
public DateTime $opensAt;
```

The key names the precision and the property names the class, so the two are
independent: `'date'` on a `DateTime` property reads back a mutable `DateTime`
at midnight. `timestamp` writes exactly what `datetime` writes; the two differ
only in the column type they are meant for.

Reading is lenient, because this is where drivers differ most. SQL Server
reports milliseconds and PostgreSQL can report an offset; whatever comes back is
read and then held to the precision the column is mapped at, so a `date` never
carries a time and a `datetime` never carries microseconds.

### Timezones

Values are converted to UTC before they are written and read back in UTC, so a
row means the same instant whatever timezone the application runs in.

```php
new DateTimeImmutable('2026-03-04 10:15:30', new DateTimeZone('Europe/Amsterdam'));
// the column holds '2026-03-04 09:15:30'
```

The value the property is given is never modified: a `DateTime` handed to
`toDatabase()` keeps its own timezone.

:::caution
UTC is applied at every precision, including `date` and `time`. A value at
`2026-03-04 00:30` in `Europe/Amsterdam` is `2026-03-03 23:30` in UTC, so a
`date` column stores `2026-03-03`. Build the value in UTC when the column means
a calendar day rather than an instant.
:::

## Null

Whether `null` is allowed is the property's decision, and it is settled before a
converter is reached:

| Situation | Result |
| --- | --- |
| Column is `NULL`, property is nullable | The property is set to `null`. |
| Column is `NULL`, property is not nullable | `HydrationException`, naming the property and column. |
| Property is `null`, property is nullable | `NULL` is written. |
| Property is `null`, property is not nullable | `PersistenceException`, naming the property. |

Only the property knows whether it accepts `null`, so only the hydrator and the
persister can answer for it; a converter is never asked to.

Every built-in converter passes `null` through in both directions anyway, so one
used on its own answers `null` with `null` rather than throwing:

```php
$types->get('int')->toDatabase(null);     // null
$types->get('datetime')->fromDatabase(null); // null
```

That makes a converter safe to call directly on a value that may be absent. It
does not make the column nullable: a `null` reaching a property that refuses it
still fails, with the exception named in the table above.

:::caution
`whereIn()` and `whereNotIn()` are the exception. `IN` cannot match `NULL`, so
they refuse a `null` among their values with `TypeConversionException` rather
than binding one that could never match.
:::

## Backed enums

A backed enum needs no registration. Ask the registry for a type it does not
know, and if that type is a backed enum it builds a `BackedEnumConverter` for it
and keeps it.

```php
enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
}
```

```php
public Role $role;
```

The column stores the backing value, `'admin'`, and reads back as `Role::Admin`.
One converter is built per enum, the first time that enum is mapped.

A backing value the column holds that the enum does not have throws
`TypeConversionException`, with the original `ValueError` attached.

:::caution
An enum with no backing type cannot be mapped. There is no value a column could
hold for `enum Role { case Admin; }`, so it throws `TypeConversionException`
naming the enum, and it throws while mapping the class rather than when a row is
read — so it fails on the first `of()` call.
:::

To map an enum some other way, register a converter under its class name and
that one is used instead:

```php
$types = new TypeRegistry([new LabelledRoleConverter()]);
```

## The registry

```php
$types = new TypeRegistry();

$types->register(new MoneyConverter());

$types->has('money');       // bool
$types->get('money');       // ColumnConverter, or TypeConversionException
```

| Method | Returns | Notes |
| --- | --- | --- |
| `register(ColumnConverter $converter)` | `void` | Keyed by the converter's own `type()`. |
| `has(string $type)` | `bool` | True for a registered type, for an aliased one, and for any backed enum. |
| `get(string $type)` | `ColumnConverter` | Follows an alias, builds one for a backed enum; throws for anything else unregistered. |
| `resolve(string $propertyType, ?string $converterType = null)` | `ColumnConverter` | The named converter, falling back to the property type, answering a mutable one for a `DateTime` property. |

Two names are aliases rather than registrations: `array` resolves to `json`, and
`DateTimeInterface` to `DateTimeImmutable`. An alias only applies when nothing is
registered under the name itself.

The constructor registers the built-in converters first and the ones you pass
after, so registering under a built-in key replaces it. That is the way to
change how every `bool` in the application is written without touching an
entity.

```php
$types = new TypeRegistry([new StrictBooleanConverter()]);
```

Next: [Writing a converter](writing-a-converter.md).
