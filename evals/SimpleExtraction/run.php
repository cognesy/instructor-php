<?php
require 'evals/boot.php';

use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Executors\Data\InferenceCases;
use Cognesy\Evals\Executors\Data\StructuredOutputData;
use Cognesy\Evals\Executors\RunInstructor;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observers\Aggregate\AggregateExperimentObserver;
use Cognesy\Evals\Observers\Evaluate\ArrayMatchEval;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Evals\SimpleExtraction\Company;
use Evals\SimpleExtraction\CompanyEval;

$data = new StructuredOutputData(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    responseModel: Company::class,
);

//Debug::setEnabled();

$experiment = new Experiment(
    cases: InferenceCases::except(
        presets: ['ollama'],
        modes: [OutputMode::JsonSchema, OutputMode::Text],
        stream: [true]
    ),
    executor: new RunInstructor($data),
    processors: [
        new CompanyEval(
            key: 'execution.is_correct',
            expectations: [
                'name' => 'ACME',
                'year' => 2020
            ]
        ),
        new ArrayMatchEval(
            expected: [
                'name' => 'ACME',
                'year' => 2020,
            ],
            metricNames: [
                'precision' => 'execution.precision',
                'recall' => 'execution.recall',
                'field_feedback' => 'execution.field_feedback',
            ]
        ),
    ],
    postprocessors: [
        new AggregateExperimentObserver(
            name: 'experiment.reliability',
            observationKey: 'execution.is_correct',
            params: ['unit' => 'fraction', 'format' => '%.2f'],
            method: NumberAggregationMethod::Mean,
        ),
        new AggregateExperimentObserver(
            name: 'experiment.mean_precision',
            observationKey: 'execution.precision',
            params: ['unit' => 'fraction', 'format' => '%.2f'],
            method: NumberAggregationMethod::Mean,
        ),
        new AggregateExperimentObserver(
            name: 'experiment.mean_recall',
            observationKey: 'execution.recall',
            params: ['unit' => 'fraction', 'format' => '%.2f'],
            method: NumberAggregationMethod::Mean,
        ),
        new AggregateExperimentObserver(
            name: 'experiment.latency_p95',
            observationKey: 'execution.timeElapsed',
            params: ['percentile' => 95, 'unit' => 'seconds'],
            method: NumberAggregationMethod::Percentile,
        ),
    ],
);

$outputs = $experiment->execute();
