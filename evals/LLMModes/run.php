<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\Evals\CompareModes;
use Cognesy\Evals\Evals\Data\EvalInput;
use Cognesy\Evals\Evals\Inference\RunInference;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Str;

$connections = [
    'azure',
    'cohere1',
    'cohere2',
    'fireworks',
    'gemini',
    'groq',
    'mistral',
    'ollama',
    'openai',
    'openrouter',
    'together',
];

$streamingModes = [
    true,
    false,
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

function evalFn(EvalInput $er) {
    $decoded = json_decode($er->response->json(), true);
    $isCorrect = match($er->mode) {
        Mode::Text => Str::contains($er->response->content(), ['ACME', '2020']),
        Mode::Tools => validateToolsData($er->response->toolsData),
        default => ('ACME' === ($decoded['name'] ?? '') && 2020 === ($decoded['year'] ?? 0)),
    };
    return $isCorrect;
}

function validateToolsData(array $data) : bool {
    return 'store_company' === ($data[0]['name'] ?? '')
        && 'ACME' === ($data[0]['arguments']['name'] ?? '')
        && 2020 === (int) ($data[0]['arguments']['year'] ?? 0);
}

//Debug::enable();

$outputs = (new CompareModes(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    schema: [],
    executorClass: RunInference::class,
    evalFn: fn(EvalInput $er) => evalFn($er),
))->executeAll(
    connections: $connections,
    modes: $modes,
    streamingModes: $streamingModes
);
