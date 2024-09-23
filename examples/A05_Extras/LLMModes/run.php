---
title: 'Working directly with LLMs'
docname: 'llm'
---

## Overview

LLM class offers access to LLM APIs and convenient methods to execute
model inference, incl. chat completions, tool calling or JSON output
generation.

LLM providers access details can be found and modified via
`/config/llm.php`.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Inference;
use Cognesy\Instructor\Utils\Str;

//$connections = ['anthropic', 'azure', 'cohere', 'fireworks', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'openrouter', 'together'];
$connections = ['gemini'];

$schema = [
    'type' => 'object',
    'properties' => [
        'answer' => [
            'type' => 'string',
            'description' => 'Answer to the question',
        ],
    ],
    'required' => ['answer'],
    'additionalProperties' => false,
];


echo "# TOOLS MODE\n\n";

echo "# NON-STREAMED INFERENCE:\n\n";
foreach ($connections as $connection) {
    $answer = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?']
            ],
            tools: [[
                'type' => 'function',
                'function' => [
                    'name' => 'answer',
                    'parameters' => $schema,
                ],
            ]],
            toolChoice: ['type' => 'function', 'function' => ['name' => 'answer']],
            options: ['max_tokens' => 64],
            mode: Mode::Tools,
        )
        ->toText();

    echo "[$connection]\n$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}

echo "# STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answerGen = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?']
            ],
            tools: [[
                'type' => 'function',
                'function' => [
                    'name' => 'answer',
                    'parameters' => $schema,
                ],
            ]],
            toolChoice: ['type' => 'function', 'function' => ['name' => 'answer']],
            options: ['stream' => true, 'max_tokens' => 64],
            mode: Mode::Tools,
        )
        ->toStream();

    echo "[$connection]\n";
    $answer = '';
    foreach ($answerGen as $chunk) {
        $answer .= $chunk;
    }
    echo "$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}




echo "### JSON_SCHEMA MODE\n\n";

echo "# NON-STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answer = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
            ],
            responseFormat: [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'answer',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            options: ['max_tokens' => 64],
            mode: Mode::JsonSchema,
        )
        ->toText();

    echo "[$connection]\n$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}

echo "# STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answerGen = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
            ],
            responseFormat: [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'answer',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            options: ['stream' => true, 'max_tokens' => 64],
            mode: Mode::JsonSchema,
        )
        ->toStream();

    echo "[$connection]\n";
    $answer = '';
    foreach ($answerGen as $chunk) {
        $answer .= $chunk;
    }
    echo "$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}



echo "### JSON MODE\n\n";

echo "# NON-STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answer = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
            ],
            responseFormat: [
                'type' => 'json_object',
                'schema' => $schema,
            ],
            options: ['max_tokens' => 64],
            mode: Mode::Json,
        )
        ->toText();

    echo "[$connection]\n$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}

echo "# STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answerGen = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                ['role' => 'user', 'content' => 'Respond with correct JSON.'],
            ],
            responseFormat: [
                'type' => 'json_object',
                'schema' => $schema,
            ],
            options: ['stream' => true, 'max_tokens' => 64],
            mode: Mode::Json,
        )
        ->toStream();

    echo "[$connection]\n";
    $answer = '';
    foreach ($answerGen as $chunk) {
        $answer .= $chunk;
    }
    echo "$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}



echo "### MD_JSON MODE\n\n";

echo "# NON-STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answer = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                ['role' => 'user', 'content' => 'Respond with correct JSON'],
                ['role' => 'user', 'content' => '```json'],
            ],
            options: ['max_tokens' => 64],
            mode: Mode::MdJson,
        )
        ->toText();

    echo "[$connection]\n$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}

echo "# STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answerGen = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
                ['role' => 'user', 'content' => 'Use JSON Schema: ' . json_encode($schema)],
                ['role' => 'user', 'content' => 'Respond with correct JSON'],
                ['role' => 'user', 'content' => '```json'],
            ],
            options: ['stream' => true, 'max_tokens' => 64],
            mode: Mode::MdJson,
        )
        ->toStream();

    echo "[$connection]\n";
    $answer = '';
    foreach ($answerGen as $chunk) {
        $answer .= $chunk;
    }
    echo "$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}


echo "### TEXT MODE\n\n";

echo "# NON-STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answer = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
            ],
            options: ['max_tokens' => 64],
            mode: Mode::Text,
        )
        ->toText();

    echo "[$connection]\n$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}

echo "# STREAMED INFERENCE:\n\n";

foreach ($connections as $connection) {
    $answerGen = (new Inference)
        ->withConnection($connection)
        ->create(
            messages: [
                ['role' => 'user', 'content' => 'What is capital of France?'],
            ],
            options: ['stream' => true, 'max_tokens' => 64],
            mode: Mode::Text,
        )
        ->toStream();

    echo "[$connection]\n";
    $answer = '';
    foreach ($answerGen as $chunk) {
        $answer .= $chunk;
    }
    echo "$answer\n\n";
    assert(Str::contains($answer, 'Paris'));
}
?>
```
