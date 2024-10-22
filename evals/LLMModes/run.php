<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompanyEval;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Aggregators\AggregateExecutionObservation;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceData;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceSchema;
use Cognesy\Instructor\Extras\Evals\Executors\RunInference;
use Cognesy\Instructor\Extras\Evals\Observers\Execution\ExecutionDuration;
use Cognesy\Instructor\Extras\Evals\Observers\Execution\ExecutionTotalTokens;
use Cognesy\Instructor\Extras\Evals\Observers\Experiment\ExperimentDuration;
use Cognesy\Instructor\Extras\Evals\Observers\Experiment\ExperimentTotalTokens;

$data = new InferenceData(
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

$experiment = new Experiment(
    cases: InferenceCases::only(
        connections: ['openai'],
        modes: [Mode::Tools, Mode::Text],
        stream: [false],
    ),
    executor: new RunInference($data),
    processors: [
        new CompanyEval(expectations: [
            'name' => 'ACME',
            'year' => 2020
        ]),
    ],
    postprocessors: [
        new AggregateExecutionObservation(
            name: 'reliability',
            observationKey: 'is_correct',
            method: NumberAggregationMethod::Mean,
        ),
    ]
);

$outputs = $experiment->execute();
