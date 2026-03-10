---
title: Environment
description: 'Where environment variables matter.'
---

## Overview

Environment variables are used at provider configuration time, not in the
structured-output API itself. Your application or preset loader reads the
variables, `LLMConfig` resolves provider settings from them, and
`StructuredOutputRuntime` uses the resulting configuration object.

This keeps the package independent from any specific framework bootstrap process.


## LLM Provider API Keys

Instructor supports many LLM providers. Configure the API keys for the ones you
plan to use in your `.env` file:

```ini
# Primary providers
OPENAI_API_KEY=''
ANTHROPIC_API_KEY=''
GEMINI_API_KEY=''

# Additional providers
A21_API_KEY=''
ANYSCALE_API_KEY=''
AZURE_OPENAI_API_KEY=''
CEREBRAS_API_KEY=''
COHERE_API_KEY=''
FIREWORKS_API_KEY=''
GROQ_API_KEY=''
MISTRAL_API_KEY=''
OLLAMA_API_KEY=''
OPENROUTER_API_KEY=''
SAMBANOVA_API_KEY=''
TOGETHER_API_KEY=''
XAI_API_KEY=''
```

Only configure the providers you plan to use. Empty keys are ignored.


## Embedding Provider Keys

If you use embedding features (via companion packages), configure these as well:

```ini
AZURE_OPENAI_EMBED_API_KEY=''
JINA_API_KEY=''
```


## Web Service Keys

Scraping and web-related add-ons use their own API keys:

```ini
JINAREADER_API_KEY=''
SCRAPFLY_API_KEY=''
SCRAPINGBEE_API_KEY=''
```

These are not required for core structured-output functionality.


## Configuration Directory Path

The `INSTRUCTOR_CONFIG_PATHS` variable tells the InstructorPHP ecosystem where to
find configuration files:

```ini
INSTRUCTOR_CONFIG_PATHS='config,vendor/cognesy/instructor-php/config'
```

This is primarily used by the CLI tooling and companion packages. The
structured-output package resolves provider presets through `LLMConfig::fromPreset()`,
which has its own path resolution logic (see [Configuration Path](configuration_path.md)).


## How It Fits Together

```
.env file
    |
    v
LLM provider preset (YAML)  <-- reads API key from env
    |
    v
LLMConfig                   <-- typed configuration object
    |
    v
StructuredOutputRuntime      <-- assembled runtime
    |
    v
StructuredOutput             <-- your application code
```

The structured-output package never reads environment variables directly. The
separation ensures that the same `LLMConfig` can be constructed from environment
variables, hardcoded values, a framework service container, or any other source.

> **Security:** Keep your `.env` file secure and never commit it to version control.
> For production, use your platform's secrets management system.
