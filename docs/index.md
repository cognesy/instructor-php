---
title: 'Instructor for PHP'
---


Instructor for PHP is a lightweight library that makes it easy to get structured outputs from Large Language Models (LLMs). Built on top of modern PHP 8.3+ features, it provides a simple, type-safe way to work with AI models.

## Key Features

- **Type Safety**: Full PHP 8.3+ type system support with strict typing
- **Multiple LLM Support**: Works with OpenAI, Anthropic, Gemini, Cohere, and more
- **Validation**: Built-in validation with custom rules and LLM-powered validation
- **Streaming**: Real-time partial object updates for better UX
- **Function Calling**: Native support for LLM function/tool calling
- **Zero Dependencies**: Clean, lightweight implementation

## Quick Example

```php
<?php
use Cognesy\Instructor\StructuredOutput;

class Person {
    public string $name;
    public int $age;
    public string $occupation;
}

$text = "Extract: Jason is 25 years old and works as a software engineer.";

$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->get();

echo $person->name; // "Jason"
echo $person->age;  // 25
echo $person->occupation; // "software engineer"
```

## Getting Started

Choose your path:

- **[Quick Start](./instructor/quickstart.md)** - Get up and running in 5 minutes
- **[Setup Guide](./instructor/setup.md)** - Detailed installation and configuration
- **[Cookbook](./cookbook/introduction.md)** - Practical examples and recipes

## Architecture

This project consists of several modular packages:

- **[Instructor](./instructor/introduction.md)** - Main structured output library
- **[Polyglot](./polyglot/overview.md)** - Low-level LLM abstraction layer  
- **[HTTP Client](./http/1-overview.md)** - Flexible HTTP client for API calls

## Community

- **GitHub**: [cognesy/instructor-php](https://github.com/cognesy/instructor-php)
- **Issues**: [Report bugs or request features](https://github.com/cognesy/instructor-php/issues)
- **Discussions**: [Join the conversation](https://github.com/cognesy/instructor-php/discussions)

---

*Instructor for PHP - Making AI outputs predictable and type-safe.*