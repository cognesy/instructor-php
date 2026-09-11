---
title: 'Exact model catalog records'
docname: 'model_catalog'
id: 'c4a1'
tags:
  - 'llm-extras'
  - 'model-catalog'
  - 'offline'
---
## Overview

Inspect model metadata by the exact `(driver, wire model)` key. An absent exact
key returns explicit unknown facts. Applications can overlay their own exact
records and pass the resulting catalog to `InferenceRuntime`.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;

$catalog = ModelCatalog::discover();
$bundled = $catalog->find('qwen', 'qwen3.8-max');

echo "BUNDLED EXACT RECORD\n";
echo json_encode([
    'key' => $bundled->key->toArray(),
    'status' => $bundled->status->value,
    'limits' => $bundled->limits->toArray(),
    'modalities' => $bundled->modalities->toArray(),
    'capabilities' => $bundled->capabilities->toArray(),
    'source' => $bundled->source,
    'catalogVersion' => $bundled->catalogVersion,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n\n";

assert($bundled->status === SupportStatus::Supported);
assert($bundled->limits->contextWindow === 1_000_000);
assert($bundled->capabilities->tools === SupportStatus::Supported);
assert($bundled->capabilities->reasoning->known);
assert($bundled->capabilities->reasoning->reasoningContentVisible);

$absent = $catalog->find('openai-compatible', 'application-model-not-listed');
$absentReasoningKnown = match ($absent->capabilities->reasoning->known) {
    true => 'yes',
    false => 'no',
};

echo "ABSENT EXACT KEY\n";
echo "Status: {$absent->status->value}\n";
echo 'Context window: ' . ($absent->limits->contextWindow ?? 'unknown') . "\n";
echo "Reasoning known: {$absentReasoningKnown}\n\n";

assert($absent->status === SupportStatus::Unknown);
assert($absent->limits->contextWindow === null);
assert(!$absent->capabilities->reasoning->known);

$applicationModels = ModelCatalog::fromArray([
    'version' => 'application-v1',
    'models' => [[
        'driver' => 'openai-compatible',
        'model' => 'private-reasoner-v1',
        'status' => 'supported',
        'limits' => ['contextWindow' => 131_072, 'maxOutput' => 16_384],
        'modalities' => ['inputText' => 'supported', 'outputText' => 'supported'],
        'capabilities' => [
            'streaming' => 'supported',
            'tools' => 'supported',
            'reasoning' => [
                'default' => 'enabled',
                'contentVisible' => true,
                'tokensVisible' => true,
            ],
        ],
        'source' => 'application',
    ]],
]);

$catalog = $catalog->overlay($applicationModels);
$private = $catalog->find('openai-compatible', 'private-reasoner-v1');
$privateReasoningVisible = match ($private->capabilities->reasoning->reasoningContentVisible) {
    true => 'yes',
    false => 'no',
};

$runtime = InferenceRuntime::fromConfig(
    config: new LLMConfig(
        apiUrl: 'http://localhost:11434/v1',
        endpoint: '/chat/completions',
        model: 'private-reasoner-v1',
        driver: 'openai-compatible',
    ),
    models: $catalog,
);
$inference = Inference::fromRuntime($runtime);

echo "APPLICATION EXACT RECORD\n";
echo "Status: {$private->status->value}\n";
echo "Source: {$private->source}\n";
echo "Reasoning content visible: {$privateReasoningVisible}\n";

assert($private->status === SupportStatus::Supported);
assert($private->source === 'application');
assert($private->capabilities->reasoning->known);
assert($inference instanceof Inference);
?>
```
