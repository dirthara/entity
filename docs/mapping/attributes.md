---
id: attributes
title: Mapping attributes
sidebar_position: 1
description: Every attribute the package reads, its options, and what happens without one.
---

# Mapping attributes

Mapping is read from attributes and from the property's own type. A class with
no attributes at all is still mappable as long as something marks the
identifier, because everything else has a convention behind it.

## The attributes

| Attribute | Target | Options |
| --- | --- | --- |
| `#[Entity]` | class | `table`, `connection` |
| `#[Id]` | property | `column`, `converter` |
| `#[Column]` | property | `column`, `converter` |
| `#[Generated]` | property | none |
| `#[Ignore]` | property | none |

## `#[Entity]`

Optional. Names the table or the connection when the convention is wrong.

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `table` | `?string` | `null` | The table name. `null` derives it from the class name. |
| `connection` | `?string` | `null` | The connection to use. `null` uses the default connection. |

```php
#[Entity(table: 'memberships', connection: 'replica')]
final class Membership { /* ... */ }
```

A class without `#[Entity]` maps the same way; the attribute exists only to
carry those two options.

The class has to be instantiable. An interface, an abstract class, a trait or an
enum throws `MappingException`.

## `#[Id]`

Marks a property as part of the identifier. At least one is required: a class
with none throws `MappingException`. Its options are the same as `#[Column]`'s,
so an identifier does not need both attributes.

```php
#[Id]
public int $id;

#[Id(column: 'user_uuid')]
public string $uuid;
```

More than one `#[Id]` makes the identifier composite, described in
[Identifiers](identifiers.md).

## `#[Column]`

Optional. Renames the column or names the converter.

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `column` | `?string` | `null` | The column name. `null` derives it from the property name. |
| `converter` | `?string` | `null` | The converter to use. `null` looks one up by the property's type. |

```php
#[Column(column: 'display_name')]
public string $displayName;

#[Column(converter: 'json')]
public array $meta;
```

The `converter` option is a registry key, not a class name. See
[Converters](../types/converters.md).

:::caution
`#[Id]` and `#[Column]` on the same property throw `MappingException`. `#[Id]`
already accepts both options, so use it alone.
:::

## `#[Generated]`

Says the database supplies the value. The column is left out of inserts and
updates.

```php
#[Id]
#[Generated]
public int $id;
```

When it is on a single-property identifier, the value the database assigned is
read back onto the entity after an insert. On any other property nothing is read
back — see
[what a write reads back](../entities.md#what-a-write-reads-back).

## `#[Ignore]`

Leaves a property out of the mapping entirely. It is never read, never written,
and its type does not need a converter.

```php
#[Ignore]
public string $transient;
```

:::caution
`#[Ignore]` together with `#[Id]`, `#[Column]` or `#[Generated]` throws
`MappingException` naming every attribute involved. Ignoring a property and
mapping it are contradictory instructions, so the package refuses rather than
picking one.
:::

## What is mapped without any attribute

Every non-static property is mapped, with its column derived from its name and
its converter looked up by its type.

Static properties are skipped, because a column belongs to a row and a static
property does not.

## What a property type has to be

| Property | Result |
| --- | --- |
| A single type with a converter, such as `string` or `?int` | Mapped. |
| A backed enum | Mapped; a converter is built for it without registration. |
| No type at all | `MappingException`: missing property type. |
| A union or intersection type, such as `string\|int` | `MappingException`: unsupported property type. |
| `mixed` | `MappingException`: unsupported property type. |
| A single type with no converter, such as `DateTimeImmutable`, a bare `array` or an enum with no backing type | `TypeConversionException`: unsupported type. |

A nullable type is recorded as nullable, which is what lets a `NULL` column read
back as `null`. See [Converters](../types/converters.md#null).

Two properties resolving to the same column throw `MappingException` naming both
of them, because one would silently overwrite the other.
