<?php

use Cognesy\Evals\ComplexExtraction\ProjectsEval;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Aggregators\AggregateExperimentObservation;
use Cognesy\Instructor\Extras\Evals\Enums\NumberAggregationMethod;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Executors\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Executors\RunInstructor;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Sequence\Sequence;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$data = new InstructorData(
    responseModel: Sequence::of(ProjectEvent::class),
    prompt: 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>',
    input: file_get_contents(__DIR__ . '/report.txt'),
    examples: require 'examples.php',
);

$experiment = new Experiment(
    cases: InferenceCases::only(
        connections: ['openai', 'anthropic', 'gemini', 'cohere'],
        modes: [Mode::Tools],
        stream: [true, false]
    ),
    executor: new RunInstructor($data),
    processors: [
        new ProjectsEval(
            expectations: ['events' => 12]
        ),
    ],
    postprocessors: [
        new AggregateExperimentObservation(
            name: 'reliability',
            observationKey: 'execution.percentFound',
            method: NumberAggregationMethod::Mean,
        )
    ],
);

$outputs = $experiment->execute();
