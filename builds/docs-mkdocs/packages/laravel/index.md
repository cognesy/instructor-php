# Instructor for Laravel

Laravel integration for [Instructor PHP](https://github.com/cognesy/instructor-php) -- the structured output library for LLMs.

## Overview

Instructor for Laravel brings the full power of structured LLM output extraction into the Laravel ecosystem. Rather than parsing free-form text responses from language models, Instructor lets you define PHP classes that describe the data you need, and the package takes care of prompting the model, validating its response, and deserializing the result into typed objects.

This package provides seamless integration between Instructor PHP and Laravel, giving you:

- **Laravel Facades** -- Use `StructuredOutput::`, `Inference::`, `Embeddings::`, and `AgentCtrl::` facades for expressive, framework-native access to LLM capabilities.
- **Dependency Injection** -- Inject `StructuredOutput`, `Inference`, or `Embeddings` directly into your classes through Laravel's service container.
- **Testing Fakes** -- Mock LLM responses with `StructuredOutput::fake()`, `Inference::fake()`, `Embeddings::fake()`, and `AgentCtrl::fake()`, complete with assertion helpers for verifying extraction calls, connection usage, and model selection.
- **Laravel HTTP Client** -- All API calls go through Laravel's `Http::` client under the hood, which means `Http::fake()` works out of the box in your test suite.
- **Event Bridge** -- Instructor's internal events are automatically dispatched through Laravel's event system, so you can attach listeners, subscribers, and queued handlers with no extra wiring.
- **Artisan Commands** -- Generate response model scaffolding with `make:response-model`, verify your API configuration with `instructor:test`, and bootstrap the package with `instructor:install`.
- **Configuration Publishing** -- Laravel-style config file with environment variable support for all settings, from API keys and model selection to HTTP timeouts and logging presets.

## Quick Start

### 1. Install the Package

```bash
composer require cognesy/instructor-laravel
# @doctest id="ff4e"
```

### 2. Configure API Key

Add to your `.env`:

```env
OPENAI_API_KEY=your-openai-api-key
// @doctest id="4d78"
```

### 3. Extract Structured Data

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;

// Define a response model
final class PersonData
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {}
}

// Extract structured data from text
$person = StructuredOutput::with(
    messages: 'John Smith is 30 years old',
    responseModel: PersonData::class,
)->get();

echo $person->name; // "John Smith"
echo $person->age;  // 30
// @doctest id="63b9"
```

## Documentation

| Guide | Description |
|-------|-------------|
| [Installation](installation.md) | Detailed installation and setup instructions |
| [Configuration](configuration.md) | Complete configuration reference |
| [Facades](facades.md) | Using StructuredOutput, Inference, Embeddings, and AgentCtrl facades |
| [Response Models](response-models.md) | Creating and using response models |
| [Code Agents](agents.md) | Using AgentCtrl for Claude Code, Codex, and OpenCode |
| [Testing](testing.md) | Testing with fakes and assertions |
| [Events](events.md) | Event handling and Laravel integration |
| [Commands](commands.md) | Artisan command reference |
| [Advanced](advanced.md) | Streaming, validation, and advanced patterns |
| [Troubleshooting](troubleshooting.md) | Common issues and solutions |

## Example: Complete Workflow

```php
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use App\ResponseModels\InvoiceData;

class InvoiceProcessor
{
    public function extractFromEmail(string $emailBody): InvoiceData
    {
        return StructuredOutput::with(
            messages: $emailBody,
            responseModel: InvoiceData::class,
            system: 'Extract invoice details from the email.',
        )->get();
    }
}

// In your test
public function test_extracts_invoice_data(): void
{
    $fake = StructuredOutput::fake([
        InvoiceData::class => new InvoiceData(
            invoiceNumber: 'INV-001',
            amount: 150.00,
            dueDate: '2024-12-31',
        ),
    ]);

    $processor = new InvoiceProcessor();
    $invoice = $processor->extractFromEmail('Invoice #INV-001...');

    $this->assertEquals('INV-001', $invoice->invoiceNumber);
    $fake->assertExtracted(InvoiceData::class);
}
// @doctest id="983e"
```

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x

## Support

- [GitHub Issues](https://github.com/cognesy/instructor-php/issues)
- [Documentation](https://docs.instructorphp.com)
