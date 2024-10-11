<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Instructor\Extras\Evals\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Data\InferenceData;
use Cognesy\Instructor\Extras\Evals\Data\InferenceSchema;
use Cognesy\Instructor\Extras\Evals\Inference\RunInference;
use Cognesy\Instructor\Extras\Evals\ExperimentSuite;
use Cognesy\Evals\LLMModes\CompanyEval;

$cases = InferenceCases::except(
    connections: [],
    modes: [],
    stream: []
);

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

$runner = new ExperimentSuite(
    executor: new RunInference($data),
    evaluator: new CompanyEval(expectations: [
        'name' => 'ACME',
        'foundingYear' => 2020
    ]),
    cases: $cases,
);

$outputs = $runner->execute();
