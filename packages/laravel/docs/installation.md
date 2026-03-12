# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- A valid API key from a supported LLM provider (OpenAI, Anthropic, Google, etc.)

## Install via Composer

```bash
composer require cognesy/instructor-laravel
```

The package uses Laravel's package auto-discovery mechanism, so the service provider and all four facades (`StructuredOutput`, `Inference`, `Embeddings`, `AgentCtrl`) are registered automatically. No manual registration is required for typical Laravel applications.

## Publish Configuration

Publish the configuration file to customize connections, extraction defaults, and other settings:

```bash
php artisan vendor:publish --tag=instructor-config
```

This creates `config/instructor.php` with all available options. The file ships with sensible defaults, so you can start using the package with just an API key and customize later as your needs grow.

## Configure API Keys

Add your LLM provider API key to `.env`. You only need the key for the provider you intend to use:

```env
# OpenAI (default)
OPENAI_API_KEY=sk-...

# Or Anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Or other providers
GEMINI_API_KEY=...
GROQ_API_KEY=...
MISTRAL_API_KEY=...
```

You can configure multiple providers simultaneously and switch between them at runtime using the `connection()` method on any facade.

## Setup by Package

The Laravel package is the Laravel integration layer for four underlying packages. You do not install them separately in a Laravel app. Instead, you configure them through Laravel's native `config/instructor.php` file and use the Laravel facades or container bindings.

The standalone `packages/config` YAML loader is not responsible for reading `config/instructor.php`. Under Laravel, the service provider reads Laravel's config repository directly and maps those values into the typed runtime config objects used by Instructor, Polyglot, and the HTTP client.

| Underlying package | Laravel surface | What to set up | Continue with |
|-------|-------------|----------------|---------------|
| `packages/instructor` | `StructuredOutput` facade and `Cognesy\Instructor\StructuredOutput` | Configure your default LLM connection, extraction defaults, and response models | [Facades](facades.md), [Response Models](response-models.md), [Configuration](configuration.md) |
| `packages/polyglot` | `Inference` and `Embeddings` facades plus `Cognesy\Polyglot\Inference\Inference` and `Cognesy\Polyglot\Embeddings\Embeddings` | Configure inference connections in `connections` and embedding models in `embeddings.connections` | [Facades](facades.md), [Configuration](configuration.md), [Events](events.md) |
| `packages/agent-ctrl` | `AgentCtrl` facade | Install the CLI agent you want to use, ensure its binary is available in `PATH`, then configure timeouts, working directory, and sandbox defaults in `agents` | [Code Agents](agents.md), [Testing](testing.md) |
| `packages/http-client` | Internal Laravel-backed transport | No separate install is required; keep `http.driver` set to `laravel` to route requests through Laravel's HTTP client and `Http::fake()` | [Configuration](configuration.md), [Testing](testing.md) |

### `packages/instructor` Under Laravel

Structured output uses the default connection from `config/instructor.php`, then applies Laravel-specific extraction defaults such as output mode and retry behavior. In practice, setup means:

- publish the config file
- set at least one LLM API key
- choose a default connection in `connections`
- define extraction defaults in `extraction`
- create response model classes and call `StructuredOutput`

### `packages/polyglot` Under Laravel

Laravel exposes Polyglot through two entry points:

- `Inference` for raw text, JSON, tool-calling, and streaming responses
- `Embeddings` for vector generation

Both use the same published config file. Inference reads from `connections`; embeddings read from `embeddings.connections`. If you already configured providers for `StructuredOutput`, raw inference usually needs no additional setup.

### `packages/agent-ctrl` Under Laravel

`AgentCtrl` does not use the LLM HTTP connections from `config/instructor.php`. It runs external agent CLIs from your Laravel application. Setup means:

- install the agent CLI you want to use
- make sure its executable is available in `PATH`
- authenticate that CLI using its own provider workflow
- set Laravel defaults for timeout, working directory, model, and sandbox in `agents`

### `packages/http-client` Under Laravel

Laravel already wires the HTTP client package into the container. All Instructor, Inference, and Embeddings calls use the Laravel-backed driver by default. Setup usually means only:

- keep `http.driver` set to `laravel`
- adjust `http.timeout` and `http.connect_timeout` if needed
- use `Http::fake()` when you want transport-level HTTP tests

## Verify Installation

Run the installation command to verify everything is configured correctly:

```bash
php artisan instructor:install
```

This will:
1. Publish the configuration if not already published
2. Check for API key configuration in your `.env` file
3. Show next steps for getting started

## Test Your Connection

Test that your API configuration is working by making a real API call:

```bash
php artisan instructor:test
```

This command displays your current configuration (connection name, driver, model, masked API key) and then performs a structured output extraction to confirm the full pipeline is operational.

To test a specific connection:

```bash
php artisan instructor:test --connection=anthropic
```

To test raw inference (without structured output extraction):

```bash
php artisan instructor:test --inference
```

## Optional: Publish Stubs

If you want to customize the response model templates used by `make:response-model`:

```bash
php artisan vendor:publish --tag=instructor-stubs
```

This publishes stubs to `stubs/instructor/` in your application root. The command will prefer your custom stubs over the package defaults when generating new response models.

## Manual Registration (Optional)

If you have disabled Laravel's package auto-discovery, manually register the service provider. In Laravel 10, add it to `config/app.php`:

```php
'providers' => [
    // ...
    Cognesy\Instructor\Laravel\InstructorServiceProvider::class,
],

'aliases' => [
    // ...
    'StructuredOutput' => Cognesy\Instructor\Laravel\Facades\StructuredOutput::class,
    'Inference' => Cognesy\Instructor\Laravel\Facades\Inference::class,
    'Embeddings' => Cognesy\Instructor\Laravel\Facades\Embeddings::class,
    'AgentCtrl' => Cognesy\Instructor\Laravel\Facades\AgentCtrl::class,
],
```

In Laravel 11 and 12, register the provider in `bootstrap/providers.php`.

## Upgrading

When upgrading to a new version, republish the configuration if there are new options:

```bash
php artisan vendor:publish --tag=instructor-config --force
```

Review the [changelog](https://github.com/cognesy/instructor-php/blob/main/CHANGELOG.md) for breaking changes before upgrading major versions.

## Next Steps

- [Configuration](configuration.md) -- Configure connections and settings
- [Facades](facades.md) -- Learn how to use the facades
- [Response Models](response-models.md) -- Create your first response model
