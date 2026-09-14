---
id: converters
title: Converters
sidebar_position: 1
description: The built-in converters, how one is chosen, nullable columns, and mapping a backed enum.
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

The read side is lenient because drivers differ about what they report for the
same column. The write side is strict, because the property's own type already
guarantees what it holds; `float` is the one exception, taking a numeric string
as well. A value a converter cannot make sense of throws
`TypeConversionException` rather than being coerced.

:::caution
`json` and `serialized` are not chosen for you. A bare `array` property has no
converter registered under `array`, so it throws `TypeConversionException` until
you name one with `#[Column(converter: 'json')]`. Which representation belongs
in the column is not something the package can guess.
:::

The `serialized` converter passes `allowed_classes: false`, so a payload can
only ever come back as arrays and scalars. Unserializing objects from a column
is how a database row turns into code execution, and no option is offered to
turn it on.

## How one is chosen

1. The `converter` option on `#[Id]` or `#[Column]`, if given.
2. Otherwise the property's type name: `string`, `int`, `float`, `bool`, or a
   class name.

The name is looked up in the `TypeRegistry`. With nothing registered under it,
the registry tries one last thing: a name that is a backed enum gets a converter
built for it. Anything else throws `TypeConversionException` naming the type,
which is what a `DateTimeImmutable` property does out of the box.

```php
#[Column(converter: 'json')]
public array $meta;     // the named converter

public bool $published; // looked up as 'bool'
```

## Null

A converter never sees `null`. The package handles it around them:

| Situation | Result |
| --- | --- |
| Column is `NULL`, property is nullable | The property is set to `null`. |
| Column is `NULL`, property is not nullable | `HydrationException`, naming the property and column. |
| Property is `null`, property is nullable | `NULL` is written. |
| Property is `null`, property is not nullable | `PersistenceException`, naming the property. |

So a converter you write only ever receives a value, and never has to answer for
`null`.

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
$types->get('money');       // TypeConverter, or TypeConversionException
```

| Method | Returns | Notes |
| --- | --- | --- |
| `register(TypeConverter $converter)` | `void` | Keyed by the converter's own `type()`. |
| `has(string $type)` | `bool` | True for a registered type and for any backed enum. |
| `get(string $type)` | `TypeConverter` | Builds one for a backed enum; throws for anything else unregistered. |
| `resolve(string $propertyType, ?string $converterType = null)` | `TypeConverter` | The named converter, falling back to the property type. |

The constructor registers the built-in converters first and the ones you pass
after, so registering under a built-in key replaces it. That is the way to
change how every `bool` in the application is written without touching an
entity.

```php
$types = new TypeRegistry([new StrictBooleanConverter()]);
```

Next: [Writing a converter](writing-a-converter.md).
