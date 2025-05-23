<?php
require 'evals/boot.php';

use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Executors\Data\InferenceCases;
use Cognesy\Evals\Executors\Data\StructuredOutputData;
use Cognesy\Evals\Executors\RunInstructor;
use Cognesy\Evals\Experiment;
use Cognesy\Evals\Observers\Aggregate\AggregateExperimentObserver;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Evals\ComplexExtraction\ProjectEvents;
use Evals\ComplexExtraction\ProjectsEval;

$data = new StructuredOutputData(
    messages: file_get_contents(__DIR__ . '/report.txt'),
    responseModel: ProjectEvents::class,
    maxTokens: 4096,
    prompt: 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>',
    examples: require 'examples.php',
);

$experiment = new Experiment(
    cases: InferenceCases::only(
        presets: ['openai', 'anthropic', 'gemini', 'cohere'],
        modes: [OutputMode::Tools],
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
