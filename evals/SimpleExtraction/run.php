<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Aggregators\FirstMetric;
use Cognesy\Instructor\Extras\Evals\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Inference\RunInstructor;
use Cognesy\Instructor\Extras\Evals\ExperimentSuite;
use Cognesy\Evals\SimpleExtraction\CompanyEval;
use Cognesy\Evals\SimpleExtraction\Company;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$data = new InstructorData(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    responseModel: Company::class,
);

$runner = new ExperimentSuite(
    cases: InferenceCases::except(
        connections: ['ollama'],
        modes: [Mode::Text],
        stream: []
    ),
    executor: new RunInstructor($data),
    evaluators: new CompanyEval(expectations: [
        'name' => 'ACME',
        'foundingYear' => 2020
    ]),
    aggregator: new FirstMetric()
);

$outputs = $runner->execute();
