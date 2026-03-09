---
title: Setup
description: 'Install the package and choose how to configure it.'
---

Most applications need only two things:

1. Install `cognesy/instructor-struct`
2. Provide provider configuration through a preset or an explicit `LLMConfig`

## Install

```bash
composer require cognesy/instructor-struct
# @doctest id="b59b"
```

## Preset-Based Setup

If your app already provides provider credentials through environment variables, start with a preset:

```php
use Cognesy\Instructor\StructuredOutput;

$structured = StructuredOutput::using('openai');
// @doctest id="c5de"
```

## Explicit Provider Configuration

Use `LLMConfig` when you want full control over driver and model:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$structured = StructuredOutput::fromConfig(
    LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini')
);
// @doctest id="9de7"
```

## Runtime Configuration

Use `StructuredOutputRuntime` when you need runtime behavior, not just provider selection:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withMaxRetries(2);

$structured = (new StructuredOutput)->withRuntime($runtime);
// @doctest id="295e"
```

## What Belongs Where

- `LLMConfig` chooses provider and model defaults
- `StructuredOutputRuntime` controls retries, output mode, events, and pipeline extensions
- `StructuredOutput` configures one request

This package does not require published config files to work. In larger framework or monorepo setups, companion packages may add presets, environment loading, or helper CLI tools, but the core API is code-first.
