---
title: Preset Management
description: Switch providers by changing presets, not request code.
---

One of Polyglot's core design principles is that your request code should remain stable while
the provider configuration changes. A **preset** is a named YAML file that bundles everything
the runtime needs -- driver type, API URL, credentials, default model, and token limits -- into
a single, swappable unit.

When you call `Inference::using('openai')`, Polyglot loads the `openai.yaml` preset from the
configuration directory and builds a fully wired runtime behind the scenes. Switching providers
is a one-line change.


## Switching Providers

Because presets encapsulate all provider details, the same request code works against any
supported backend:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$prompt = Messages::fromString('Explain dependency injection in one sentence.');

$openai    = Inference::using('openai')->withMessages($prompt)->get();
$anthropic = Inference::using('anthropic')->withMessages($prompt)->get();
$gemini    = Inference::using('gemini')->withMessages($prompt)->get();
```

You can also override the model on a per-request basis without creating a new preset:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->with(
        messages: Messages::fromString('What is the capital of France?'),
        model: 'gpt-4.1',
    )
    ->get();
```


## Understanding Presets vs. Driver Types

It is important to distinguish between a **preset name** and a **driver type**. A preset name
(e.g. `openai`, `ollama`, `custom-local`) is an arbitrary label for a YAML configuration file.
A driver type (e.g. `openai`, `anthropic`, `openai-compatible`) refers to the underlying
protocol implementation that Polyglot uses to communicate with the API.

Multiple presets can share the same driver. For example, you might create a `local-llama` preset
that uses the `openai-compatible` driver pointed at a local Ollama instance, and a `together`
preset that also uses the `openai-compatible` driver pointed at the Together AI API.

Polyglot ships with the following driver types:

| Driver | Providers |
|---|---|
| `openai` | OpenAI |
| `openai-responses` | OpenAI (Responses API) |
| `anthropic` | Anthropic |
| `gemini` | Google Gemini (native API) |
| `gemini-oai` | Google Gemini (OpenAI-compatible API) |
| `azure` | Azure OpenAI |
| `bedrock-openai` | AWS Bedrock (OpenAI-compatible) |
| `a21` | AI21 Labs |
| `cerebras` | Cerebras |
| `cohere` | Cohere |
| `deepseek` | DeepSeek |
| `fireworks` | Fireworks AI |
| `glm` | GLM |
| `groq` | Groq |
| `huggingface` | Hugging Face |
| `inception` | Inception |
| `meta` | Meta |
| `minimaxi` | MiniMaxi |
| `mistral` | Mistral AI |
| `openrouter` | OpenRouter |
| `openresponses` | Open Responses |
| `perplexity` | Perplexity |
| `qwen` | Qwen |
| `sambanova` | SambaNova |
| `xai` | xAI (Grok) |
| `openai-compatible` | Any OpenAI-compatible API (Ollama, Together, Moonshot, etc.) |


## Implementing Fallbacks

Polyglot does not impose a fallback policy. Fallback behavior belongs in application code,
where you have the context to decide which providers to try and how to handle failures:

```php
<?php

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Messages\Messages;

function withFallback(array $presets, Messages $prompt): string {
    $lastException = null;

    foreach ($presets as $preset) {
        try {
            return Inference::using($preset)
                ->withMessages($prompt)
                ->get();
        } catch (HttpRequestException $e) {
            $lastException = $e;
            // Optionally log the failure before trying the next provider
        }
    }

    throw new \RuntimeException(
        'All providers failed. Last error: ' . $lastException?->getMessage()
    );
}

$response = withFallback(
    presets: ['openai', 'anthropic', 'gemini'],
    prompt: Messages::fromString('What is the capital of France?'),
);
```

This pattern gives you full control over retry logic, logging, and error handling at each
step of the fallback chain.


## Cost-Aware Provider Selection

You can route requests to different presets based on the complexity or importance of each task.
This pattern lets you reserve expensive models for critical work while using cheaper alternatives
for simpler queries:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

class CostAwareRouter {
    private array $tiers = [
        'low'    => ['preset' => 'ollama',  'model' => 'llama3'],
        'medium' => ['preset' => 'mistral', 'model' => 'mistral-small-latest'],
        'high'   => ['preset' => 'openai',  'model' => 'gpt-4.1'],
    ];

    public function ask(string $question, string $tier = 'medium'): string {
        $provider = $this->tiers[$tier] ?? $this->tiers['medium'];

        return Inference::using($provider['preset'])
            ->with(messages: Messages::fromString($question), model: $provider['model'])
            ->get();
    }
}

$router = new CostAwareRouter();

// Simple question -- use low-cost tier
echo $router->ask('What is 2+2?', 'low');

// Moderate complexity -- use medium tier
echo $router->ask('Explain monads in simple terms.', 'medium');

// High-stakes analysis -- use premium tier
echo $router->ask('Analyze the ethical implications of AI in healthcare.', 'high');
```


## Task-Based Provider Selection

Different providers may excel at different tasks. You can map task types to the most appropriate
preset, routing creative writing to one model and code generation to another:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

class TaskRouter {
    private array $routes = [
        'creative' => 'anthropic',
        'factual'  => 'openai',
        'code'     => 'gemini',
        'default'  => 'openai',
    ];

    public function ask(string $question, string $taskType = 'default'): string {
        $preset = $this->routes[$taskType] ?? $this->routes['default'];

        return Inference::using($preset)
            ->withMessages(Messages::fromString($question))
            ->get();
    }
}

$router = new TaskRouter();

echo $router->ask('Write a short poem about the ocean.', 'creative');
echo $router->ask('What is the capital of France?', 'factual');
echo $router->ask('Write a PHP function to reverse a string.', 'code');
```

> **Tip:** You can combine cost-aware and task-based routing. For example, use a cheap local
> model for simple factual lookups but route complex creative tasks to a premium cloud provider.


## Reusing an Inference Instance

Each call to `Inference::using()` loads the preset YAML and builds a new runtime. If you plan
to issue many requests against the same provider, create the instance once and reuse it:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$inference = Inference::using('openai');

$answer1 = $inference->withMessages(Messages::fromString('What is PHP?'))->get();
$answer2 = $inference->withMessages(Messages::fromString('What is Laravel?'))->get();
```

Because `Inference` uses immutable builder methods (each call returns a new copy), sharing a
single instance across concurrent requests is safe.
