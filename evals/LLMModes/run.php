<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompareModes;
use Cognesy\Evals\LLMModes\EvalRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Str;

$connections = [
    'anthropic',
    'azure',
    'cohere',
    'fireworks',
    'gemini',
    'groq',
    'mistral',
    'ollama',
    'openai',
    'openrouter',
    'together'
];

$streamingModes = [true];
$modes = [
    Mode::Text,
//    Mode::MdJson,
//    Mode::Json,
//    Mode::JsonSchema,
//    Mode::Tools,
];

(new CompareModes(
    query: 'Our user Jason is 28 yo. What is the name and age of the user?',
    evalFn: fn(EvalRequest $er) => Str::contains($er->answer, ['28', 'Jason']),
    //debug: true,
))->executeAll(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);