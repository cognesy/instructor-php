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

$streamingModes = [
    true,
    false
];

$modes = [
    Mode::Text,
    Mode::MdJson,
    Mode::Json,
    Mode::JsonSchema,
    Mode::Tools,
];

//
// NOT SUPPORTED BY PROVIDERS
//
// groq, Mode::JsonSchema, stream
// groq, Mode::Json, stream
// azure, Mode::JsonSchema, sync|stream
//

(new CompareModes(
    query: [
        ['role' => 'system', 'content' => 'Our company ACME was founded in 2020.'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    evalFn: fn(EvalRequest $er) => Str::contains($er->answer, ['2020', 'ACME']),
    //debug: true,
))->executeAll(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);
