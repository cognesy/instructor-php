<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Inference\RunInstructor;
use Cognesy\Instructor\Extras\Evals\ExperimentSuite;
use Cognesy\Evals\SimpleExtraction\CompanyEval;
use Cognesy\Evals\SimpleExtraction\Company;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$cases = InferenceCases::except(
    connections: [],
    modes: [Mode::Text],
    stream: []
);

$data = new InstructorData(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        //['role' => 'user', 'content' => 'EXAMPLE CONTEXT: Sony was established in 1946 by Akio Morita.'],
        //['role' => 'user', 'content' => 'EXAMPLE RESPONSE: ```json{"name":"Sony","year":1899}```'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    responseModel: Company::class,
);

$runner = new ExperimentSuite(
    executor: new RunInstructor($data),
    evaluator: new CompanyEval(expectations: [
        'name' => 'ACME',
        'foundingYear' => 2020
    ]),
    cases: $cases,
);

$outputs = $runner->execute();
