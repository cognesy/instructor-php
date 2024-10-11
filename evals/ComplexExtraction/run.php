<?php

use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Data\InferenceCases;
use Cognesy\Instructor\Extras\Evals\Data\InstructorData;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Inference\RunInstructor;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Evals\ExperimentSuite;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$cases = InferenceCases::get(
    connections: [],
    modes: [],
    stream: []
);

class Company {
    public string $name;
    public int $foundingYear;
}

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

class CompanyEval implements CanEvaluateExperiment
{
    public function evaluate(Experiment $experiment) : Metric {
        /** @var Person $decoded */
        $person = $experiment->response->value();
        $result = $person->name === 'ACME'
            && $person->foundingYear === 2020;
        return new BooleanCorrectness($result);
    }
}

//Debug::enable();

//$report = file_get_contents(__DIR__ . '/report.txt');
//$examples = require 'examples.php';
//$prompt = 'Extract a list of project events with all the details from the provided input in JSON format using schema: <|json_schema|>';
//$responseModel = Sequence::of(ProjectEvent::class);

$runner = new ExperimentSuite(
    executor: new RunInstructor($data),
    evaluator: new CompanyEval(),
);

$outputs = $runner->execute($cases);
