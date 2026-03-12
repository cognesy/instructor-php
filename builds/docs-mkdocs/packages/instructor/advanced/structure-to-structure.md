---
title: 'Structure to Structure'
description: 'Use structured data as input for LLM-driven transformations.'
---

Instructor can accept structured data as input, not just raw text. This enables powerful object-to-object transformations where the LLM acts as an intelligent mapping and enrichment layer between two data shapes.

## Basic Usage

Use `withInput()` to pass arrays or objects as input. Instructor serializes them into messages automatically.

```php
use Cognesy\Instructor\StructuredOutput;

$result = (new StructuredOutput)
    ->withInput(['name' => 'Jane', 'bio' => 'Engineer from Berlin'])
    ->withResponseClass(Profile::class)
    ->get();
// @doctest id="07c1"
```

## Object-to-Object Transformation

The most common use case is transforming one object into another, using the LLM to interpret, translate, or enrich the data along the way.

```php
use Cognesy\Instructor\StructuredOutput;

class Email {
    public function __construct(
        public string $address = '',
        public string $subject = '',
        public string $body = '',
    ) {}
}

$email = new Email(
    address: 'joe@gmail.com',
    subject: 'Status update',
    body: 'Your account has been updated.',
);

$translation = (new StructuredOutput)
    ->withInput($email)
    ->withPrompt('Translate the text fields of email to Spanish. Keep other fields unchanged.')
    ->withResponseClass(Email::class)
    ->get();

// Email {
//     address: "joe@gmail.com",
//     subject: "Actualización de estado",
//     body: "Su cuenta ha sido actualizada."
// }
// @doctest id="78a3"
```

The input object is serialized into the message content, and the LLM produces a new object of the specified response model class. The `prompt` parameter provides instructions for how to transform the data.

## Array Input

Arrays work the same way. This is useful when your source data comes from a database query, API response, or form submission.

```php
$result = (new StructuredOutput)
    ->withInput([
        'product' => 'Wireless Mouse',
        'features' => ['Bluetooth 5.0', '1600 DPI', 'USB-C charging'],
        'price' => 29.99,
    ])
    ->withPrompt('Generate a marketing-friendly product listing from this data.')
    ->withResponseClass(ProductListing::class)
    ->get();
// @doctest id="f694"
```

## String Input

Plain strings are also accepted. In this case, `withInput()` behaves the same as `withMessages()`.

```php
$result = (new StructuredOutput)
    ->withInput('Jane Doe, 31, Berlin')
    ->withResponseClass(Person::class)
    ->get();
// @doctest id="03be"
```

## When to Use Structure-to-Structure

This pattern is most valuable when:

- **Translating or localizing** structured content while preserving the data shape
- **Enriching** existing data with LLM-generated content (e.g., adding descriptions, summaries, or tags)
- **Mapping** between different schemas, using the LLM to handle ambiguity that rule-based mapping cannot
- **Normalizing** messy or inconsistent structured data into a clean format
