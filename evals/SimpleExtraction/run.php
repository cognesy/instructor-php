<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Extras\Evals\Data\ExperimentData;
use Cognesy\Instructor\Extras\Evals\Inference\InferenceParams;
use Cognesy\Instructor\Extras\Evals\Inference\RunInstructor;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Evals\Runner;
use Cognesy\Instructor\Extras\Evals\Utils\Combination;

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
    mapping: InferenceParams::class,
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

class CompanyEval implements CanEvaluateExperiment
{
    public function evaluate(Experiment $experiment) : Metric {
        /** @var Person $decoded */
        $person = $experiment->response->value();
        $result = $person->name === 'ACME'
            && $person->foundingYear === 2020;
        return new BooleanCorrectness($result);
    }
}

$data = (new ExperimentData)->withInstructorConfig(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    responseModel: Company::class,
);

//Debug::enable();

//$report = file_get_contents(__DIR__ . '/report.txt');
//$examples = require 'examples.php';
//$prompt = 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>';
//$responseModel = Sequence::of(ProjectEvent::class);

$outputs = (new Runner(
    data: $data,
    runner: new RunInstructor(),
    evaluation: new CompanyEval(),
))->execute(
    combinations: $combinations
);
