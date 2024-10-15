<?php

use Cognesy\Evals\SimpleExtraction\Company;
use Cognesy\Evals\SimpleExtraction\CompanyEval;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Executors\RunInstructor;

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

$experiment = new Experiment(
    cases: InferenceCases::except(
        connections: ['ollama'],
        modes: [Mode::Text],
        stream: []
    ),
    executor: new RunInstructor($data),
    evaluators: new CompanyEval(expectations: [
        'name' => 'ACME',
        'year' => 2020
    ]),
);

$outputs = $experiment->execute();
