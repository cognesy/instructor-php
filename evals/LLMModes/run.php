<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompareModes;
use Cognesy\Instructor\Enums\Mode;

//$connections = ['anthropic', 'azure', 'cohere', 'fireworks', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'openrouter', 'together'];
$connections = ['anthropic', 'azure', 'cohere', 'fireworks', 'gemini', 'groq', 'mistral', 'ollama', 'openai', 'together'];
$streamingModes = [false];
$modes = [
    Mode::Text,
    Mode::MdJson,
    Mode::Json,
    Mode::Tools,
    Mode::JsonSchema,
];

(new CompareModes(
    query: 'What is the capital of France?',
    expected: 'Paris',
    //debug: true,
))->executeFor(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);