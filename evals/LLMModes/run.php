<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompareModes;
use Cognesy\Evals\LLMModes\EvalRequest;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Str;

$connections = [
//    'anthropic',
//    'azure',
    'cohere1',
//    'fireworks',
//    'gemini',
//    'groq',
//    'mistral',
//    'ollama',
//    'openai',
//    'openrouter',
//    'together'
];

$streamingModes = [
    true,
//    false
];

$modes = [
//    Mode::Text,
//    Mode::MdJson,
//    Mode::Json,
//    Mode::JsonSchema,
    Mode::Tools,
];

//
// NOT SUPPORTED BY PROVIDERS
//
// groq, Mode::JsonSchema, stream
// groq, Mode::Json, stream
// azure, Mode::JsonSchema, sync|stream
//

function evalFn(EvalRequest $er) {
    $json = Json::find($er->answer) ?: '[]';
    $decoded = json_decode($json, true) ?: [];
    $isCorrect = match($er->mode) {
        Mode::Text => Str::contains($er->answer, ['ACME', '2020']),
        Mode::Tools => validateToolsData($er->response->toolsData),
        default => ('ACME' === ($decoded['name'] ?? '') && 2020 === ($decoded['year'] ?? 0)),
    };
    return $isCorrect;
}

function validateToolsData(array $data) : bool {
    return 'store_company' === ($data[0]['name'] ?? '')
        && 'ACME' === ($data[0]['arguments']['name'] ?? '')
        && 2020 === ($data[0]['arguments']['year'] ?? 0);
}

(new CompareModes(
    query: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    evalFn: fn(EvalRequest $er) => evalFn($er),
    //debug: true,
))->executeAll(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);
