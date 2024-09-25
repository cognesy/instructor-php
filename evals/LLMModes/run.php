<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompareModes;
use Cognesy\Evals\LLMModes\EvalRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Str;

$connections = [
//    'anthropic',
//    'azure',
//    'cohere',
//    'fireworks',
    'gemini',
//    'groq',
//    'mistral',
//    'ollama',
//    'openai',
//    'openrouter',
//    'together'
];

$streamingModes = [false];
$modes = [
    Mode::Text,
    Mode::MdJson,
    Mode::Json,
    Mode::JsonSchema,
    Mode::Tools,
];

(new CompareModes(
    query: 'What is the capital of France?',
    evalFn: fn(EvalRequest $er) => Str::contains($er->answer, 'Paris'),
    //debug: true,
))->executeAll(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);