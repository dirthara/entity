---
id: defining
title: Defining relations
sidebar_position: 1
description: The four relation attributes, which table holds the foreign key, and the names derived for you.
---

# Defining relations

A relation is a property carrying one of four attributes. The property is not a
column: it is left out of the mapped properties entirely, and what fills it is
[loading](loading.md).

```php
#[Entity]
final class Book
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[BelongsToOne]
    public Writer $writer;

    #[HasOne]
    public ?Jacket $jacket;

    /**
     * @var Collection<int, Chapter>
     */
    #[HasMany(target: Chapter::class)]
    public Collection $chapters;

    /**
     * @var Collection<int, Topic>
     */
    #[BelongsToMany(target: Topic::class)]
    public Collection $topics;
}
```

## Which side holds the foreign key

The attribute names which side of the relation this entity is on, and that is
what decides which table carries the key.

| Attribute | The key lives on | Means |
| --- | --- | --- |
| `#[BelongsToOne]` | **this** entity's table | This row points at one other row. |
| `#[HasOne]` | the **target's** table | One other row points back at this one. |
| `#[HasMany]` | the **target's** table | Many other rows point back at this one. |
| `#[BelongsToMany]` | a join table | Rows on both sides point at each other through a third table. |

So `Book` holding a `writer_id` column is a `BelongsToOne`, and a `Jacket` row
holding a `book_id` column is the `Book`'s `HasOne`.

:::note
The names follow the usual convention: *belongs to* means this row carries the
key, and *has* means the other side carries it. If you have mapped relations
before, these four should need no relearning.
:::

## `#[BelongsToOne]`

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `foreignKey` | `?string` | `null` | The column on this table. `null` derives it from the property name and the target's identifier column. |
| `loading` | `RelationLoading` | `Explicit` | `Eager` loads it with every query for this entity. |
| `target` | `?string` | `null` | The related class. `null` takes it from the property's type. |

```php
#[BelongsToOne]
public Writer $writer;          // books.writer_id

#[BelongsToOne]
public ?Writer $editor;         // books.editor_id, may be NULL

#[BelongsToOne(foreignKey: 'written_by')]
public Writer $author;          // books.written_by
```

A nullable property means the column may be `NULL`, and that is what lets you
[dissociate](writing.md#to-one-relations) it.

## `#[HasOne]`

Options are identical to `#[BelongsToOne]`'s, but the key is derived from
**this** entity instead, because it lives on the other table.

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `foreignKey` | `?string` | `null` | The column on the target's table. `null` derives it from this class name and this identifier column. |
| `loading` | `RelationLoading` | `Explicit` | `Eager` loads it with every query for this entity. |
| `target` | `?string` | `null` | The related class. `null` takes it from the property's type. |

```php
#[HasOne]
public ?Jacket $jacket;         // jackets.book_id
```

## `#[HasMany]`

`target` is required: a property typed as a collection or array says nothing about what
is in it.

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `target` | `string` | — | The related class. Required. |
| `foreignKey` | `?string` | `null` | The column on the target's table. `null` derives it from this class name and this identifier column. |
| `loading` | `RelationLoading` | `Explicit` | `Eager` loads it with every query for this entity. |

```php
#[HasMany(target: Chapter::class)]
public Collection $chapters;    // chapters.book_id
```

## `#[BelongsToMany]`

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `target` | `string` | — | The related class. Required. |
| `table` | `?string` | `null` | The join table. `null` derives it from both class names. |
| `foreignKey` | `?string` | `null` | The join table column pointing at this entity. |
| `relatedForeignKey` | `?string` | `null` | The join table column pointing at the target. |
| `loading` | `RelationLoading` | `Explicit` | `Eager` loads it with every query for this entity. |

```php
#[BelongsToMany(target: Topic::class)]
public Collection $topics;      // book_topic (book_id, topic_id)
```

## The names derived for you

With `DefaultNamingStrategy`:

| Derived | From | Example |
| --- | --- | --- |
| A `BelongsToOne` key | the property name and the target's identifier column | `writer` on `Writer(id)` → `writer_id` |
| A `HasOne` or `HasMany` key | this class name and this identifier column | `Book(id)` → `book_id` |
| A join table | both class names, snake cased, sorted, singular | `Book` and `Topic` → `book_topic` |

These are the conventional defaults, so a schema already built to them needs no
renaming: `#[BelongsToOne] public Writer $writer` expects `books.writer_id`, and
`#[HasMany(target: Chapter::class)]` on `Book` expects `chapters.book_id`.

For a key derived from a class name — a `HasOne`, a `HasMany`, or either side
of a join table — an identifier column that already names its entity is not
repeated. An `Account` whose identifier column is `account_uuid` gives
`account_uuid`, not `account_account_uuid`. A `BelongsToOne` key is built from
the property name instead, so `#[BelongsToOne] public Account $owner` gives
`owner_account_uuid`.

The join table is sorted so both sides derive the same name, whichever end you
declare the relation from. `NoNamingStrategy` sorts it too, and otherwise joins
the names as they are written.

## What a property type has to be

| Relation | Property type | Result |
| --- | --- | --- |
| To one | The related class, or an interface it implements | Mapped; the target is taken from the type. |
| To one | Nullable, such as `?Writer` | Mapped, and the relation may be absent. |
| To one | A built-in type, such as `string` | `MappingException`: invalid relation type. |
| To many | `array` | Mapped; a loaded relation is written as a plain array. |
| To many | `Collection` or `iterable` | Mapped; a loaded relation is written as an `ImmutableCollection`. |
| To many | Anything else, such as `string` | `MappingException`: invalid to-many type. |

Naming a `target` for a to-one relation narrows it, which is how you point an
interface-typed property at one implementation:

```php
#[BelongsToOne(target: Person::class)]
public Owner $owner;
```

A `target` the property type would not accept throws `MappingException`.

## What is refused, and why

| Mapping | Result |
| --- | --- |
| Two relation attributes on one property | `MappingException`: multiple relations. |
| A relation together with `#[Id]`, `#[Column]`, `#[Generated]` or `#[Ignore]` | `MappingException` naming every attribute involved. |
| A relation on an entity whose identifier is composite | `MappingException`: the entity cannot own the relation. |
| A `BelongsToOne` or `BelongsToMany` pointing at an entity whose identifier is composite | `MappingException` naming the relation and the target. |
| A target class that does not exist, or has no identifier | `MappingException`. |
| A derived foreign key that a mapped column already takes | `MappingException`: duplicate column. |
| A `BelongsToMany` pointing at its own entity without naming both keys | `MappingException`: both keys derive the same name. |

That last one needs the keys told apart, because both are columns of one join
table:

```php
#[BelongsToMany(
    target: self::class,
    table: 'category_relations',
    foreignKey: 'category_id',
    relatedForeignKey: 'related_category_id',
)]
public Collection $related;
```

:::note
A relation is read while the class is mapped, so any of these fails on the first
`of()` call for that class rather than when a row is read.
:::
