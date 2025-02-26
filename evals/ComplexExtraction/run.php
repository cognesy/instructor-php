<?php
require 'evals/boot.php';

use Cognesy\Addons\Evals\Enums\NumberAggregationMethod;
use Cognesy\Addons\Evals\Executors\Data\InferenceCases;
use Cognesy\Addons\Evals\Executors\Data\InstructorData;
use Cognesy\Addons\Evals\Executors\RunInstructor;
use Cognesy\Addons\Evals\Experiment;
use Cognesy\Addons\Evals\Observers\Aggregate\AggregateExperimentObserver;
use Cognesy\Evals\ComplexExtraction\ProjectEvents;
use Cognesy\Evals\ComplexExtraction\ProjectsEval;
use Cognesy\Polyglot\LLM\Enums\Mode;

$data = new InstructorData(
    responseModel: ProjectEvents::class,
    maxTokens: 4096,
    prompt: 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>',
    input: file_get_contents(__DIR__ . '/report.txt'),
    examples: require 'examples.php',
);

$experiment = new Experiment(
    cases: InferenceCases::only(
        connections: ['openai', 'anthropic', 'gemini', 'cohere'],
        modes: [Mode::Tools],
        stream: [false]
    ),
    executor: new RunInstructor($data),
    processors: [
        new ProjectsEval(
            key: 'execution.fractionFound',
            expectations: ['events' => 12]
        ),
    ],
    postprocessors: [
        new AggregateExperimentObserver(
            name: 'experiment.mean_completeness',
            observationKey: 'execution.fractionFound',
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
