<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompanyEval;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceData;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceSchema;
use Cognesy\Instructor\Extras\Evals\Executors\RunInference;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Observers\Aggregate\AggregateExperimentObserver;
use Cognesy\Instructor\Utils\Debug\Debug;

$data = new InferenceData(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'Store the name and founding year of our company'],
    ],
    schema: new InferenceSchema(
        toolName: 'store_company',
        toolDescription: 'Store company information',
        schema: [
            'type' => 'object',
            'description' => 'Company information',
            'properties' => [
                'founding_year' => [
                    'type' => 'integer',
                    'description' => 'Founding year',
                ],
                'company_name' => [
                    'type' => 'string',
                    'description' => 'Company name',
                ],
            ],
            'required' => ['company_name', 'founding_year'],
            'additionalProperties' => false,
        ]
    ),
);

//Debug::enable();

$experiment = new Experiment(
    //cases: InferenceCases::only(['cerebras'], [Mode::JsonSchema, Mode::Json, Mode::MdJson, Mode::Tools, Mode::Text], [true]),
    cases: InferenceCases::all(),
    executor: new RunInference($data),
    processors: [
        new CompanyEval(
            key: 'execution.is_correct',
            expectations: [
                'company_name' => 'ACME',
                'founding_year' => 2020
            ]),
    ],
    postprocessors: [
        new AggregateExperimentObserver(
            name: 'experiment.reliability',
            observationKey: 'execution.is_correct',
            params: ['unit' => 'fraction', 'format' => '%.2f'],
            method: NumberAggregationMethod::Mean,
        ),
    ]
);

$outputs = $experiment->execute();
