<?php
require 'evals/boot.php';

use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Executors\Data\InferenceCases;
use Cognesy\Evals\Executors\Data\InferenceData;
use Cognesy\Evals\Executors\Data\InferenceSchema;
use Cognesy\Evals\Executors\RunInference;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observers\Aggregate\AggregateExperimentObserver;
use Cognesy\Http\Debug\Debug;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Settings;
use Evals\LLMModes\CompanyEval;

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

//Debug::setEnabled();
//$connections = array_keys(Settings::get('llm', 'connections'));
$connections = [
    'openai',
    'deepseek-r'
];
$modes = [
    OutputMode::MdJson,
//    OutputMode::Text,
//    OutputMode::JsonSchema,
//    OutputMode::MdJson,
//    OutputMode::Tools,
//    OutputMode::Unrestricted
];
$stream = [
    false,
    true
];

$experiment = new Experiment(
    cases: InferenceCases::only($connections, $modes, $stream),
    //cases: InferenceCases::all(),
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
