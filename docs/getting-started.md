---
title: 'Getting Started'
---


Get structured data from LLMs in under 5 minutes.

## Prerequisites

- PHP 8.3 or higher
- Composer
- An API key from any [supported LLM provider](/packages/instructor/misc/llm_providers)

## Installation

```bash
composer require cognesy/instructor-php
```

## Configuration

Create a `.env` file in your project root with your API key:

```bash
# For OpenAI (default)
OPENAI_API_KEY=sk-your-api-key-here

# Or for other providers
ANTHROPIC_API_KEY=your-key
GEMINI_API_KEY=your-key
GROQ_API_KEY=your-key
```

## Your First Extraction

### Step 1: Define Your Data Structure

Create a PHP class that represents the data you want to extract:

```php
<?php

class Movie {
    public string $title;
    public int $year;
    public string $director;
    /** @var string[] */
    public array $genres;
}
```

### Step 2: Extract Data

Use Instructor to extract structured data from text:

```php
<?php

use Cognesy\Instructor\StructuredOutput;

$text = "The Matrix is a 1999 science fiction film directed by the Wachowskis.
         It stars Keanu Reeves and explores themes of reality and consciousness.";

$movie = (new StructuredOutput)
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->get();

echo $movie->title;    // "The Matrix"
echo $movie->year;     // 1999
echo $movie->director; // "The Wachowskis"
print_r($movie->genres); // ["science fiction"]
```

### Step 3: Add Validation (Optional)

Use Symfony Validator attributes for automatic validation:

```php
<?php

use Symfony\Component\Validator\Constraints as Assert;

class Movie {
    #[Assert\NotBlank]
    public string $title;

    #[Assert\Range(min: 1888, max: 2030)]
    public int $year;

    #[Assert\NotBlank]
    public string $director;

    /** @var string[] */
    #[Assert\Count(min: 1)]
    public array $genres;
}
```

If validation fails, Instructor automatically retries with the LLM, providing error feedback so it can self-correct.

## Using Different Providers

Switch providers with a single method call:

```php
<?php
// OpenAI (default)
$result = (new StructuredOutput)
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->get();

// Anthropic Claude
$result = (new StructuredOutput)
    ->using('anthropic')
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->get();

// Google Gemini
$result = (new StructuredOutput)
    ->using('gemini')
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->get();

// Local Ollama
$result = (new StructuredOutput)
    ->using('ollama')
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->get();
```

## Processing Images

Extract data from images using vision-capable models:

```php
<?php

use Cognesy\Instructor\StructuredOutput;

class Receipt {
    public string $vendor;
    public float $total;
    public string $date;
    /** @var LineItem[] */
    public array $items;
}

class LineItem {
    public string $description;
    public float $amount;
}

$receipt = (new StructuredOutput)
    ->using('openai')
    ->withResponseClass(Receipt::class)
    ->withImages(['path/to/receipt.jpg'])
    ->withMessages("Extract all information from this receipt")
    ->get();

echo $receipt->vendor; // "Whole Foods"
echo $receipt->total;  // 47.23
```

## Streaming Responses

Get partial results as they arrive:

```php
<?php

use Cognesy\Instructor\StructuredOutput;

$movie = (new StructuredOutput)
    ->withResponseClass(Movie::class)
    ->onPartialUpdate(function($partial) {
        // $partial contains incrementally populated fields
        echo "Title so far: " . ($partial->title ?? 'loading...') . "\n";
    })
    ->with(
        messages: $text,
        options: ['stream' => true]
    )
    ->get();

// $movie is the final, validated result
```

## Next Steps

You now have the basics. Here's where to go next:

| Goal | Resource |
|------|----------|
| Learn core concepts | [Why Instructor](why-instructor) |
| See practical examples | [Cookbook](/cookbook) |
| Explore all features | [Features Overview](features) |
| Configure providers | [LLM Providers](/packages/instructor/misc/llm_providers) |
| Advanced validation | [Validation Guide](/packages/instructor/essentials/validation) |

## Common Patterns

### Extract Multiple Items

```php
<?php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$movies = (new StructuredOutput)
    ->withResponseClass(Sequence::of(Movie::class))
    ->withMessages("List the top 3 Nolan films")
    ->get();

// $movies is iterable and has array-like access
foreach ($movies as $movie) {
    echo $movie->title;
}
```

### Add Context with System Messages

```php
<?php
$movie = (new StructuredOutput)
    ->withResponseClass(Movie::class)
    ->withMessages([
        ['role' => 'system', 'content' => 'You are a film expert. Be precise with dates.'],
        ['role' => 'user', 'content' => $text]
    ])
    ->get();
```

### Set Max Retries

```php
<?php
$movie = (new StructuredOutput)
    ->withResponseClass(Movie::class)
    ->withMessages($text)
    ->withMaxRetries(3)
    ->get();
```

---

**Need help?** Check out the [Cookbook](/cookbook) for 60+ working examples, or [open an issue](https://github.com/cognesy/instructor-php/issues) on GitHub.
