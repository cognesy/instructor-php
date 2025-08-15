---
title: 'Provider-Specific Issues'
description: 'Learn how to troubleshoot provider-specific issues when using Polyglot.'
---

Each LLM provider has unique quirks and issues. This section covers common provider-specific issues and how to resolve them.


## OpenAI

1. **Organization IDs**: Set the organization ID if using a shared account
```php
// @doctest id="5f77"
// In config/llm.php
'metadata' => [
    'organization' => 'org-your-organization-id',
],
```

2. **API Versions**: Pay attention to API version changes
```php
// @doctest id="ca63"
// Updates to OpenAI API may require changes to your code
// Monitor OpenAI's release notes for changes
```

## Anthropic

1. **Message Format**: Anthropic uses a different message format
```php
// @doctest id="ed3a"
// Polyglot handles this automatically, but be aware when debugging
```

2. **Tool Support**: Tool support has specific requirements
```php
// @doctest id="d716"
// When using tools with Anthropic, check their latest documentation
// for supported features and limitations
```

## Mistral

1. **Rate Limits**: Mistral has strict rate limits on free tier
```php
// @doctest id="c5a8"
// Implement more aggressive rate limiting for Mistral
```


## Ollama

1. **Local Setup**: Ensure Ollama is properly installed and running
```bash
# @doctest id="5215"
# Check if Ollama is running
curl http://localhost:11434/api/version
```

2. **Model Availability**: Download models before using them
```bash
# @doctest id="57ca"
# Pull a model before using it
ollama pull llama2
```
