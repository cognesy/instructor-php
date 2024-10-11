<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Extras\Evals\Data\ExperimentData;
use Cognesy\Instructor\Extras\Evals\Data\InferenceSchema;
use Cognesy\Instructor\Extras\Evals\Inference\InferenceParams;
use Cognesy\Instructor\Extras\Evals\Inference\RunInference;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Evals\Runner;
use Cognesy\Instructor\Extras\Evals\Utils\Combination;
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

$combinations = Combination::generator(
    mapping: InferenceParams::class,
    sources: [
        'isStreaming' => $streamingModes,
        'mode' => $modes,
        'connection' => $connections,
    ],
);

class CompanyEval implements CanEvaluateExperiment
{
    public function evaluate(Experiment $experiment) : Metric {
        $decoded = json_decode($experiment->response->json(), true);
        $isCorrect = match ($experiment->mode) {
            Mode::Text => Str::contains($experiment->response->content(), ['ACME', '2020']),
            Mode::Tools => $this->validateToolsData($experiment->response->toolsData),
            default => ('ACME' === ($decoded['name'] ?? '') && 2020 === ($decoded['year'] ?? 0)),
        };
        return new BooleanCorrectness($isCorrect);
    }

    private function validateToolsData(array $data) : bool {
        return 'store_company' === ($data[0]['name'] ?? '')
            && 'ACME' === ($data[0]['arguments']['name'] ?? '')
            && 2020 === (int) ($data[0]['arguments']['year'] ?? 0);
    }
}

//Debug::enable();

$data = (new ExperimentData)->withInferenceConfig(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    schema: new InferenceSchema(
        toolName: 'store_company',
        toolDescription: 'Store company information',
        schema: [
            'type' => 'object',
            'description' => 'Company information',
            'properties' => [
                'year' => [
                    'type' => 'integer',
                    'description' => 'Founding year',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Company name',
                ],
            ],
            'required' => ['name', 'year'],
            'additionalProperties' => false,
        ]
    ),
);

$evaluator = new Runner(
    data: $data,
    runner: new RunInference(),
    evaluation: new CompanyEval(),
);

$outputs = $evaluator->execute(
    combinations: $combinations
);
