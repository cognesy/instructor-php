---
title: Prompting
description: 'Prompting guidelines and structural patterns for better structured output.'
---

Instructor shifts much of the "prompting" work into the response model itself. A well-designed
class with clear property names, PHPDoc comments, and typed fields often communicates intent
more effectively than a lengthy system prompt.

## General Principles

- **State the task plainly.** A short, direct instruction outperforms a wall of text.
- **Provide the source material.** Pass the text you want analyzed as part of the message.
- **Let the schema do the heavy lifting.** Prefer changing the response model before adding more prompt detail.

## Use PHPDoc Comments as Instructions

The model receives your class structure as a JSON Schema. PHPDoc comments on the class and its
properties become `description` fields in that schema, giving the model precise guidance without
bloating the prompt.

```php
/** Extract the user's profile from the provided text. */
final class UserDetail {
    public int $age;
    public string $name;
    /** Assign the most appropriate role based on context. */
    public ?Role $role = null;
}
// @doctest id="38e6"
```

## Nullable Fields for Optional Data

Use PHP's nullable types and set a default of `null` to signal that a field is truly optional.
This prevents the model from inventing values when the source text does not contain the information.

```php
final class UserDetail {
    public int $age;
    public string $name;
    public ?string $nickname = null;
}
// @doctest id="c931"
```

## Enums for Standardized Fields

Use backed enums whenever a property has a fixed set of valid values. Always include a fallback
case so the model can signal uncertainty rather than forcing an incorrect choice.

```php
enum Role: string {
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Student = 'student';
    case Other = 'other';
}

final class UserDetail {
    public int $age;
    public string $name;
    /** Correctly assign one of the predefined roles to the user. */
    public Role $role;
}
// @doctest id="d8a7"
```

## Chain of Thought

Adding a "reasoning" or "chain of thought" field encourages the model to think step-by-step
before producing the final answer. This works especially well for classification, entity
extraction, and any task where intermediate reasoning improves accuracy.

```php
final class Role {
    /** Think step by step to determine the correct title. */
    public string $chainOfThought;
    public string $title;
}

final class UserDetail {
    public int $age;
    public string $name;
    public Role $role;
}
// @doctest id="a3bc"
```

You can make chain of thought modular by embedding it inside nested components rather than at
the top level of the response model.

## Reiterate Long Instructions

For complex extraction rules, restate the instructions in the field description. This keeps the
guidance close to the point where the model generates its output.

```php
/** Extract the role based on the following rules: <your rules> */
final class Role {
    /** Restate the instructions and rules to correctly determine the title. */
    public string $instructions;
    public string $title;
}
// @doctest id="257e"
```

## Handle Arbitrary Properties

When the set of properties is not known ahead of time, use a list of key-value pairs.

```php
final class Property {
    public string $key;
    public string $value;
}

final class UserDetail {
    public int $age;
    public string $name;
    /** @var Property[] Extract any other relevant properties. */
    public array $properties;
}
// @doctest id="0c2d"
```

### Limiting List Length

Control the number of extracted items by stating the constraint in the PHPDoc comment and
optionally enforcing it with validation.

```php
final class UserDetail {
    public int $age;
    public string $name;
    /** @var Property[] Extracted properties, no more than 3. */
    public array $properties;
}
// @doctest id="b19d"
```

### Consistent Keys Across Records

When extracting multiple records with arbitrary properties, instruct the model to use consistent
key names so downstream code can process them uniformly.

```php
final class UserDetails {
    /** @var UserDetail[] Use consistent key names for properties across users. */
    public array $users;
}
// @doctest id="8f94"
```

## Define Entity Relationships

When relationships exist between extracted entities, model them explicitly with identifiers and
reference arrays.

```php
final class UserDetail {
    /** Unique identifier for each user. */
    public int $id;
    public int $age;
    public string $name;
    public string $role;
    /** @var int[] IDs of coworkers this user collaborates with. */
    public array $coworkers;
}

final class UserRelationships {
    /** @var UserDetail[] Capture all users and their relationships. */
    public array $users;
}
// @doctest id="ccb4"
```

## Reuse Components Across Contexts

The same class can appear in multiple properties with different PHPDoc descriptions, giving each
usage its own semantic meaning.

```php
final class TimeRange {
    /** The start time in hours. */
    public int $startTime;
    /** The end time in hours. */
    public int $endTime;
}

final class UserDetail {
    public string $name;
    /** Time range during which the user is working. */
    public TimeRange $workTime;
    /** Time range reserved for leisure activities. */
    public TimeRange $leisureTime;
}
// @doctest id="37ad"
```

## Error Handling with Wrapper Models

Create a wrapper class that can hold either a successful result or an error message. This lets
you stay within structured output even when the input is ambiguous or invalid.

```php
final class MaybeUser {
    public ?UserDetail $result = null;
    public bool $error = false;
    public ?string $errorMessage = null;

    public function get(): ?UserDetail {
        return $this->error ? null : $this->result;
    }
}
// @doctest id="71eb"
```
