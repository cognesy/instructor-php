---
title: Overview
description: 'Polyglot is a PHP library that provides a unified API to access various LLM API providers.'
---

Polyglot is a PHP library that provides a unified API for interacting with various Large Language Model (LLM) providers. It allows developers to build applications that use LLMs without being locked into a specific provider or having to rewrite code when switching between providers.

The core philosophy behind Polyglot is to create a consistent, provider-agnostic interface that abstracts away the differences between LLM APIs, while still allowing access to provider-specific features when needed. This enables developers to:

- Write code once and use it with any supported LLM provider
- Easily switch between providers without changing application code
- Use different providers in different environments (development, testing, production)
- Fall back to alternative providers if one becomes unavailable

Polyglot was developed as part of the Instructor for PHP library, which focuses on structured outputs from LLMs, but can also be used as a standalone library for general LLM interactions.




## Key Features

### Unified LLM API

Polyglot's primary feature is its unified API that works across multiple LLM providers:

- Consistent interface for making inference or embedding requests
- Common message format across all providers
- Standardized response handling
- Unified error handling


### Framework-Agnostic

Polyglot is designed to work with any PHP framework or even in plain PHP applications. It does not depend on any specific framework, making it easy to integrate into existing projects.

- Compatible with Laravel, Symfony, CodeIgniter, and others
- Can be used in CLI scripts or web applications
- Lightweight and easy to install


### Comprehensive Provider Support

Polyglot supports a wide range of LLM providers, including:

- OpenAI (GPT models)
- Anthropic (Claude models)
- Google Gemini (native and OpenAI compatible)
- Mistral AI
- Azure OpenAI
- Cohere
- And many others (see full list below)

### Multiple Interaction Modes

Polyglot supports various modes of interaction with LLMs:

- **Text mode**: Simple text completion/chat
- **JSON mode**: Structured JSON responses
- **JSON Schema mode**: Responses validated against a schema
- **Tools mode**: Function/tool calling for task execution

### Streaming Support

Real-time streaming of responses is supported across compatible providers:

- Token-by-token streaming
- Progress handling
- Partial response accumulation

### Embeddings Generation

Beyond text generation, Polyglot includes support for vector embeddings:

- Generate embeddings from text
- Support for multiple embedding providers
- Utilities for finding similar documents

### Configuration Flexibility

Polyglot offers a flexible configuration system:

- Configure multiple providers simultaneously
- Environment-based configuration
- Runtime provider switching
- Per-request customization

### Middleware and Extensibility

The library is built with extensibility in mind:

- HTTP client middleware for customization
- Event system for request/response monitoring
- Ability to add custom providers



## Use Cases

Polyglot is a good choice for a variety of use cases:

- **Applications requiring LLM provider flexibility**: Switch between providers based on cost, performance, or feature needs
- **Multi-environment deployments**: Use different LLM providers in development, staging, and production
- **Redundancy and fallback**: Implement fallback strategies when a provider is unavailable
- **Hybrid approaches**: Combine different providers for different tasks based on their strengths
- **Local + cloud development**: Use local models (via Ollama) for development and cloud providers for production



## Supported Providers

### Inference Providers

Polyglot currently supports the following LLM providers for chat completion:

- **A21**: API access to Jamba models
- **Anthropic**: Claude family of models
- **Microsoft Azure**: Azure-hosted OpenAI models
- **Cerebras**: Cerebras LLMs
- **Cohere**: Command models (both native and OpenAI compatible interfaces)
- **Deepseek**: Deepseek models including reasoning capabilities
- **Google Gemini**: Google's Gemini models (both native and OpenAI compatible)
- **Groq**: High-performance inference platform
- **Minimaxi**: MiniMax models
- **Mistral**: Mistral AI models
- **Moonshot**: Kimi models
- **Ollama**: Self-hosted open source models
- **OpenAI**: GPT models family
- **OpenRouter**: Multi-provider routing service
- **Perplexity**: Perplexity models
- **SambaNova**: SambaNova hosted models
- **Together**: Together AI hosted models
- **xAI**: xAI's Grok models

### Embeddings Providers

For embeddings generation, Polyglot supports:

- **Microsoft Azure**: Azure-hosted OpenAI embeddings
- **Cohere**: Cohere embeddings models
- **Google Gemini**: Google's embedding models
- **Jina**: Jina embeddings
- **Mistral**: Mistral embedding models
- **Ollama**: Self-hosted embedding models
- **OpenAI**: OpenAI embeddings
