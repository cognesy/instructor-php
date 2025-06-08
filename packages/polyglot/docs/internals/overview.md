---
title: Overview of Architecture
description: A detailed look at Polyglot's internal architecture
---

This section provides a detailed look at Polyglot's internal architecture.

Understanding the core components, interfaces, and design patterns will help
you extend the library, contribute to its development, or build your own
integrations with new LLM providers.


## Core Architecture

Polyglot is built on a modular, layered architecture that separates concerns
and promotes extensibility. The high-level architecture consists of:

1. **Public API Layer**: Classes like `Inference` and `Embeddings` that provide a unified interface for applications
2. **Provider Abstraction Layer**: Adapters, drivers, and formatters that translate between the unified API and provider-specific formats
3. **HTTP Client Layer**: A flexible HTTP client with middleware support for communicating with LLM APIs
4. **Configuration Layer**: Configuration management for different providers and models


```
+---------------------+    +---------------------+
|      Inference      |    |     Embeddings      |
+---------------------+    +---------------------+
            |                        |
+---------------------+    +---------------------+
|  InferenceRequest   |    | EmbeddingsRequest   |
+---------------------+    +---------------------+
            |                        |
+---------------------+    +---------------------+
|   PendingInference  |    |  PendingEmbeddings  |
+---------------------+    +---------------------+
            |                        |
+---------------------+    +---------------------+
|  InferenceDrivers   |    |  EmbeddingsDrivers  |
+---------------------+    +---------------------+
            |                        |
+------------------------------------------------+
|               HTTP Client Layer                |
+------------------------------------------------+
                         |
+------------------------------------------------+
|           Provider-specific API Calls          |
+------------------------------------------------+
```
