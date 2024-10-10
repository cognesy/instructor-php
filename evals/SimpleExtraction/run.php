<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Combination;
use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Extras\Evals\Evaluator;
use Cognesy\Instructor\Extras\Evals\Instructor\RunInstructor;
use Cognesy\Instructor\Extras\Evals\Mappings\ConnectionModes;
use Cognesy\Instructor\Utils\Debug\Debug;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$connections = [
//    'azure',
//    'cohere1',
    'cohere2',
//    'fireworks',
//    'gemini',
//    'groq',
//    'mistral',
//    'ollama',
//    'openai',
//    'openrouter',
//    'together',
];

$streamingModes = [
    false,
    true,
];

$modes = [
    Mode::MdJson,
    Mode::Json,
    Mode::JsonSchema,
    Mode::Tools,
];

$combinations = Combination::generator(
    mapping: ConnectionModes::class,
    sources: [
        'isStreaming' => $streamingModes,
        'mode' => $modes,
        'connection' => $connections,
    ],
);

class Company {
    public string $name;
    public int $foundingYear;
}

function evalFn(EvalInput $er) {
    /** @var Person $decoded */
    $person = $er->response->value();
    return $person->name === 'ACME'
        && $person->foundingYear === 2020;
}

//Debug::enable();

//$report = file_get_contents(__DIR__ . '/report.txt');
//$examples = require 'examples.php';
//$prompt = 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>';
//$responseModel = Sequence::of(ProjectEvent::class);

$outputs = (new Evaluator(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    schema: Company::class,
    runner: new RunInstructor(),
    evalFn: fn(EvalInput $er) => evalFn($er),
))->execute(
    combinations: $combinations
);
