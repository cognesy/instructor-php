<?php

use Cognesy\Evals\SimpleExtraction\Company;
use Cognesy\Evals\SimpleExtraction\CompanyEval;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Aggregators\AggregateExperimentObservation;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
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
    cases: InferenceCases::only(
        connections: ['openai', 'anthropic'],
        modes: [Mode::Tools],
        stream: [false]
    ),
    executor: new RunInstructor($data),
    processors: [
        new CompanyEval(expectations: [
            'name' => 'ACME',
            'year' => 2020
        ]),
    ],
    postprocessors: [
        new AggregateExperimentObservation(
            name: 'experiment.reliability',
            observationKey: 'execution.is_correct',
            method: NumberAggregationMethod::Mean,
        ),
        new AggregateExperimentObservation(
            name: 'latency',
            observationKey: 'execution.timeElapsed',
            params: ['percentile' => 95],
            method: NumberAggregationMethod::Percentile,
        ),
    ],
);

$outputs = $experiment->execute();
