# Artisan Commands

The package registers three Artisan commands to help with installation, testing, and scaffolding. All commands are registered automatically when the application is running in console mode.

## instructor:install

Sets up the Instructor package in your Laravel application. This is the recommended first step after installing the Composer package.

```bash
php artisan instructor:install
# @doctest id="2554"
```

### What It Does

1. **Publishes the configuration file** (`config/instructor.php`) using the `instructor-config` publish tag
2. **Checks for API key configuration** by scanning your `.env` file for `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` entries
3. **Displays next steps** including how to create your first response model and test the installation

If no API keys are detected, the command displays a warning with instructions for adding them.

### Options

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing configuration files (passed through to `vendor:publish`) |

### Example Output

```
Installing Instructor for Laravel...

Publishing configuration... done
Checking API key configuration... done

Next steps:

  1. Configure your API keys in .env:
     OPENAI_API_KEY=your-key-here

  2. Create a response model:
     php artisan make:response-model PersonData

  3. Extract structured data:
     $person = Instructor::with(
         messages: "John is 30 years old",
         responseModel: PersonData::class,
     )->get();

  4. Test your installation:
     php artisan instructor:test

Instructor installed successfully!
// @doctest id="1935"
```

---

## instructor:test

Tests your Instructor installation and API configuration by making a real API call. This verifies that your API key is valid, the network connection works, and the full extraction pipeline (or raw inference pipeline) is operational.

```bash
php artisan instructor:test
# @doctest id="3ec7"
```

### What It Does

1. **Displays current configuration** -- connection name, driver, model, and a masked version of the API key
2. **Makes a test API call** -- either a structured output extraction (default) or a raw inference call
3. **Verifies the response** -- confirms the result contains the expected data

For the structured output test, the command extracts a simple name-and-age pair from a test sentence. For the inference test, it sends "Reply with just the word 'pong'" and checks the response.

### Options

| Option | Description |
|--------|-------------|
| `--connection=` | Test a specific configured connection instead of the default |
| `--inference` | Test raw inference instead of structured output extraction |

### Examples

```bash
# Test default connection
php artisan instructor:test

# Test specific connection
php artisan instructor:test --connection=anthropic

# Test raw inference
php artisan instructor:test --inference
# @doctest id="8661"
```

### Example Output

```
Testing Instructor installation...

Connection ......................................... openai
Driver ............................................. openai
Model .............................................. gpt-4o-mini
API Key ............................................ sk-a...xyz1 done

Testing structured output extraction... done

Structured output test completed!
// @doctest id="8fff"
```

Use the `-v` flag for verbose output, which includes a full stack trace if the test fails.

---

## make:response-model

Generates a new response model class with typed constructor properties, docblock descriptions, and the correct namespace. The generated class is ready to use with `StructuredOutput::with(responseModel: ...)` immediately.

```bash
php artisan make:response-model {name}
# @doctest id="cbb0"
```

### Arguments

| Argument | Description |
|----------|-------------|
| `name` | The name of the response model class (e.g., `PersonData`, `InvoiceDetails`) |

### Options

| Option | Description |
|--------|-------------|
| `--collection`, `-c` | Create a collection response model with a parent class containing an array of child item objects |
| `--nested`, `-n` | Create a nested response model with child object properties (Contact, Address) |
| `--description=`, `-d` | Set the class docblock description (defaults to a TODO placeholder) |
| `--force`, `-f` | Overwrite an existing file |

### Examples

#### Basic Response Model

```bash
php artisan make:response-model PersonData
# @doctest id="d205"
```

Creates `app/ResponseModels/PersonData.php`:

```php
<?php

declare(strict_types=1);

namespace App\ResponseModels;

/**
 * TODO: Add description for this response model
 */
final class PersonData
{
    public function __construct(
        /** The name of the person */
        public readonly string $name,

        /** The age of the person in years */
        public readonly int $age,

        /** Optional email address */
        public readonly ?string $email = null,
    ) {}
}
// @doctest id="3c80"
```

#### Collection Response Model

```bash
php artisan make:response-model ProductList --collection
# @doctest id="5866"
```

Creates a model with an array of typed items, plus a companion item class in the same file:

```php
final class ProductList
{
    public function __construct(
        /** List of extracted items */
        public readonly array $items,
    ) {}
}

final class ProductListItem
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}
}
// @doctest id="2d06"
```

#### Nested Objects Response Model

```bash
php artisan make:response-model CompanyProfile --nested
# @doctest id="3015"
```

Creates a model with nested Contact and Address objects:

```php
final class CompanyProfile
{
    public function __construct(
        public readonly string $title,
        public readonly CompanyProfileContact $contact,
        public readonly ?CompanyProfileAddress $address = null,
    ) {}
}

final class CompanyProfileContact
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
    ) {}
}

final class CompanyProfileAddress
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        public readonly ?string $postalCode = null,
    ) {}
}
// @doctest id="c030"
```

#### With Description

```bash
php artisan make:response-model Invoice --description="Represents an invoice extracted from PDF documents"
# @doctest id="a109"
```

Creates a model with the specified description replacing the TODO placeholder in the docblock.

---

## Customizing Stubs

Publish the stubs to customize the templates used by `make:response-model`:

```bash
php artisan vendor:publish --tag=instructor-stubs
# @doctest id="66c1"
```

This copies stubs to `stubs/instructor/` in your application root:

```
stubs/instructor/
+-- response-model.stub
+-- response-model.collection.stub
+-- response-model.nested.stub
// @doctest id="d0ac"
```

The command checks for published stubs first, falling back to the package defaults only when no custom stub is found.

### Stub Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{ namespace }}` | The fully qualified namespace for the class |
| `{{ class }}` | The class name |
| `{{ description }}` | The class description (from `--description` or the default TODO) |

---

## Creating Custom Commands

Build your own Artisan commands that use the facades for domain-specific extraction tasks:

```php
// app/Console/Commands/ExtractInvoiceCommand.php
namespace App\Console\Commands;

use App\ResponseModels\InvoiceData;
use Cognesy\Instructor\Laravel\Facades\StructuredOutput;
use Illuminate\Console\Command;

class ExtractInvoiceCommand extends Command
{
    protected $signature = 'invoice:extract {file}';
    protected $description = 'Extract invoice data from a file';

    public function handle(): int
    {
        $content = file_get_contents($this->argument('file'));

        $invoice = StructuredOutput::with(
            messages: $content,
            responseModel: InvoiceData::class,
        )->get();

        $this->info("Invoice Number: {$invoice->number}");
        $this->info("Amount: \${$invoice->amount}");
        $this->info("Due Date: {$invoice->dueDate}");

        return self::SUCCESS;
    }
}
// @doctest id="f59b"
```

---

## Command Reference

| Command | Description |
|---------|-------------|
| `instructor:install` | Install and configure the package |
| `instructor:test` | Test API configuration with a real API call |
| `make:response-model` | Generate a response model class |
