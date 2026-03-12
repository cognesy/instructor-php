---
title: Provider-Specific Issues
description: Diagnose and resolve issues unique to individual LLM providers.
---

Each LLM provider has its own API conventions, authentication requirements, and behavioral quirks. Polyglot normalizes many of these differences through its driver system, but it cannot erase real capability gaps between providers. This page covers the most common provider-specific issues and how to address them.

## General Approach

If a request works with one provider and fails with another:

1. Remove any provider-specific fields from `options`.
2. Test with plain text output (no tools, no response format, no streaming).
3. Add features back one at a time to identify which one causes the failure.

Polyglot handles message format translation, endpoint routing, and authentication header differences automatically. However, custom `options` entries are passed through to the provider as-is, which can cause errors if the target provider does not recognize them.

## OpenAI

### Organization and Project IDs

If you use a shared OpenAI account, you may need to set the organization ID in the preset metadata:

```yaml
# config/llm/presets/openai.yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
metadata:
  organization: 'org-your-organization-id'
  project: 'proj-your-project-id'
```

### API Changes

OpenAI periodically updates its API. If requests that previously worked start failing, check OpenAI's changelog for breaking changes. Model deprecations are the most common cause.

### OpenAI Responses API

Polyglot also supports the OpenAI Responses API through the `openai-responses` driver and preset. This uses a different endpoint (`/responses`) and message format. Make sure you use the correct preset:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// Standard Chat Completions API
$text = Inference::using('openai')
    ->withMessages('Hello')
    ->get();

// Responses API (different driver)
$text = Inference::using('openai-responses')
    ->withMessages('Hello')
    ->get();
```

## Anthropic

### Message Format

Anthropic uses a different message format than OpenAI. Polyglot handles this translation automatically through the `anthropic` driver. You do not need to format messages differently -- just use the standard Polyglot API.

### API Version Header

The Anthropic API requires an `anthropic-version` header. This is configured in the preset metadata:

```yaml
# config/llm/presets/anthropic.yaml
driver: anthropic
apiUrl: 'https://api.anthropic.com/v1'
apiKey: '${ANTHROPIC_API_KEY}'
endpoint: /messages
metadata:
  apiVersion: '2023-06-01'
  beta: prompt-caching-2024-07-31
```

If you see errors about unsupported API versions, update the `apiVersion` value in your preset.

### System Messages

Anthropic handles system messages differently from OpenAI -- they are sent as a separate `system` parameter rather than as a message in the conversation. Polyglot's Anthropic driver handles this automatically.

## Google Gemini

Polyglot supports Gemini through two drivers:

- **`gemini`** -- uses Google's native Generative AI API with its own message format
- **`gemini-oai`** -- uses Google's OpenAI-compatible endpoint

The native Gemini API has different request and response structures. If you experience issues with one driver, try the other:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;

// Native Gemini API
$text = Inference::using('gemini')
    ->withMessages('Hello')
    ->get();

// OpenAI-compatible Gemini endpoint
$text = Inference::using('gemini-oai')
    ->withMessages('Hello')
    ->get();
```

## Mistral

### Rate Limits on Free Tier

Mistral enforces strict rate limits on free-tier accounts. If you see HTTP 429 errors frequently, consider upgrading your plan or implementing more aggressive throttling in your application.

### Model Names

Mistral model identifiers can change between versions. Verify that the model name in your preset matches a currently available model.

## Ollama (Local Models)

### Service Must Be Running

Ollama runs as a local service. Ensure it is installed and running before making requests:

```bash
# Check if Ollama is running
curl http://localhost:11434/api/version

# Start Ollama if not running
ollama serve
```

### Pull Models Before Use

Models must be downloaded before they can be used:

```bash
# Pull a model
ollama pull llama3

# List available models
ollama list
```

### Default Endpoint

The Ollama preset uses the OpenAI-compatible endpoint at `http://localhost:11434/v1/chat/completions`. If Ollama is running on a different host or port, update the `apiUrl` in your preset.

### Feature Limitations

Local models through Ollama may not support all features that cloud providers offer. Tool calling, JSON schema mode, and streaming behavior can vary by model. Test each feature individually.

## Azure OpenAI

Azure OpenAI uses a different URL structure and authentication mechanism. The `azure` driver handles these differences:

```yaml
# config/llm/presets/azure.yaml
driver: azure
apiUrl: 'https://your-resource.openai.azure.com/openai'
apiKey: '${AZURE_OPENAI_API_KEY}'
endpoint: '/deployments/your-deployment/chat/completions'
model: gpt-4
```

Azure deployments use deployment names rather than model names in the endpoint URL. Ensure the `endpoint` field includes the correct deployment name.

## AWS Bedrock

Use the `bedrock-openai` driver for Bedrock's OpenAI-compatible endpoint.
The current 2.0 implementation authenticates with a bearer API key. AWS
SigV4 credential signing is not implemented in Polyglot yet.

```yaml
# config/llm/presets/aws-bedrock.yaml
driver: bedrock-openai
apiUrl: 'https://bedrock-runtime.us-east-1.amazonaws.com/openai/v1'
apiKey: '${AWS_BEDROCK_API_KEY}'
endpoint: /chat/completions
model: anthropic.claude-3-haiku-20240307-v1:0
metadata:
  region: '${AWS_BEDROCK_REGION:-us-east-1}'
```

## Cohere

The `cohere` driver supports Cohere's v2 Chat API. Cohere uses a different message and usage format that the driver translates automatically.

## Other Providers

Polyglot includes drivers for many additional providers including Deepseek, Fireworks, Groq, HuggingFace, Cerebras, Perplexity, SambaNova, Together, XAI, Qwen, GLM, Inception, and MiniMaxi. Each has a corresponding preset file in the `config/llm/presets/` directory.

For providers that follow the OpenAI API format, the `openai-compatible` driver provides broad compatibility. If a provider does not have a dedicated driver, try configuring it with `driver: openai-compatible` and adjusting the `apiUrl`, `endpoint`, and `apiKey` fields.
