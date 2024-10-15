<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');
$loader->add('Cognesy\\Evals\\', __DIR__ . '../../evals/');

use Cognesy\Evals\LLMModes\CompanyEval;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceData;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceSchema;
use Cognesy\Instructor\Extras\Evals\Executors\RunInference;

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
    cases: InferenceCases::except(
        connections: [],
        modes: [],
        stream: [],
    ),
    executor: new RunInference($data),
    evaluators: new CompanyEval(expectations: [
        'name' => 'ACME',
        'year' => 2020
    ]),
);

$outputs = $experiment->execute();
