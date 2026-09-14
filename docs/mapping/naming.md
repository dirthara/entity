---
id: naming
title: Naming
sidebar_position: 3
description: How table and column names are derived, and how to replace the convention.
---

# Naming

`DefaultNamingStrategy` derives a table name from the class's short name and a
column name from the property name. Either can be overridden per class with
`#[Entity(table: ...)]` or per property with `#[Column(column: ...)]`.

## Columns

A property name is snake-cased.

| Property | Column |
| --- | --- |
| `id` | `id` |
| `displayName` | `display_name` |
| `HTTPStatus` | `http_status` |

An acronym keeps together: the boundary is placed before the last capital of a
run, so `HTTPStatus` does not become `h_t_t_p_status`.

## Tables

A class's short name is snake-cased and then pluralised. The namespace is not
part of it.

| Class | Table |
| --- | --- |
| `Article` | `articles` |
| `UserProfile` | `user_profiles` |
| `HTTPRequest` | `http_requests` |
| `Oauth2Client` | `oauth2_clients` |
| `Category` | `categories` |
| `Day` | `days` |
| `Status` | `statuses` |
| `Box` | `boxes` |
| `Church` | `churches` |
| `Dish` | `dishes` |
| `Person` | `people` |
| `SalesPerson` | `sales_people` |
| `Series` | `series` |
| `Analysis` | `analyses` |

The rules, in the order they are tried:

1. An irregular plural, matched on the whole name or on its last underscored
   word. That is what turns `sales_person` into `sales_people`.
2. A consonant before a final `y` becomes `ies`, so `category` pluralises and
   `day` does not.
3. A name ending in `s`, `x`, `z`, `ch` or `sh` takes `es`.
4. Everything else takes `s`.

The irregular list covers `person`, `child`, `man`, `woman`, `mouse`, `goose`,
`tooth`, `foot`, `ox`, `analysis`, `basis`, `crisis`, `thesis`, `deer`, `sheep`,
`species` and `series`.

:::tip
The list is deliberately short. English pluralisation has no end of special
cases, and a name the strategy gets wrong is better fixed with
`#[Entity(table: 'people')]` on that one class than by growing a dictionary
every application pays for.
:::

## Replacing the convention

`NamingStrategy` has two methods. Implement it and pass it to the
`MetadataFactory`.

```php
use Dirthara\Entity\Naming\NamingStrategy;

final readonly class SingularTableNaming implements NamingStrategy
{
    public function table(string $entityShortName): string
    {
        return strtolower($entityShortName);
    }

    public function column(string $property): string
    {
        return $property;
    }
}

$factory = new MetadataFactory(new SingularTableNaming(), $types);
```

| Method | Receives | Returns |
| --- | --- | --- |
| `table(string $entityShortName)` | The class's short name, without its namespace. | The table name. |
| `column(string $property)` | The property name. | The column name. |

A strategy is only consulted where an attribute did not already answer, so
`#[Column(column: 'display_name')]` wins over whatever `column()` would say.
