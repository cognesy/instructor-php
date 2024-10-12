<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Inference\RunInstructor;
use Cognesy\Instructor\Extras\Evals\ExperimentSuite;
use Cognesy\Instructor\Extras\Evals\Metrics\PercentageCorrectness;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Features\LLM\Data\Usage;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

class Company {
    public string $name;
    public int $foundingYear;
}

class CompanyEval implements CanEvaluateExperiment
{
    public array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Experiment $experiment) : Evaluation {
        $expectedEvents = $this->expectations['events'];
        /** @var Sequence $events */
        $events = $experiment->response->value();
        $result = ($expectedEvents - count($events->list)) / $expectedEvents;
        return new Evaluation(
            metric: new PercentageCorrectness('found', $result),
            feedback: Feedback::none(),
            usage: Usage::none(),
        );
    }
}

$report = file_get_contents(__DIR__ . '/report.txt');
$examples = require 'examples.php';
$prompt = 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>';
$responseModel = Sequence::of(ProjectEvent::class);

$data = new InstructorData(
    messages: [
        ['role' => 'user', 'content' => 'YOUR GOAL: Use tools to store the information from context based on user questions.'],
        ['role' => 'user', 'content' => 'CONTEXT: Our company ACME was founded in 2020.'],
        ['role' => 'user', 'content' => 'What is the name and founding year of our company?'],
    ],
    responseModel: Company::class,
);

$runner = new ExperimentSuite(
    cases: InferenceCases::only(
        connections: ['openai', 'anthropic', 'gemini', 'cohere'],
        modes: [Mode::Tools],
        stream: [true, false]
    ),
    executor: new RunInstructor($data),
    evaluators: new CompanyEval(expectations: ['events' => 12]),
);

$outputs = $runner->execute();
