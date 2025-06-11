---
title: Inference Class
description: 'How to use the Inference class in Polyglot for LLM requests'
---

The `Inference` class is the primary interface for making requests to LLM providers in Polyglot.
It provides methods for creating and sending requests, managing connections, and processing
responses.


## Creating an Inference Instance

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create a basic inference instance with default settings
$inference = new Inference()->withMessages('What is the capital of France?')->get();

// Create an inference instance with a specific connection
$inference = new Inference()->using('openai')->withMessages('What is the capital of France?')->get();
```


## Key Methods

The `Inference` class provides several key methods:

- `create()`: Sends a request to the LLM provider
- `withConnection()`: Specifies which connection to use
- `withConfig()`: Sets a custom configuration
- `withHttpClient()`: Specifies a custom HTTP client
- `withDebug()`: Enables or disables debug mode
- `withCachedContext()`: Sets cached context for improved performance
