# Language programs

```php
<?php

use Cognesy\Instructor\Extras\Signature\Signature;
use Cognesy\Instructor\Extras\Task\PredictTask;
use Cognesy\Instructor\Instructor;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

$predict = new PredictTask('capital -> country (as country code), country_population:int (in mln)', (new Instructor));
$country = $predict->with(['capital' => 'Paris'])->get();
dump($country);

class AnswerCorrectness extends Signature {
    #[InputField]
    public string $question;

    #[InputField]
    #[Description('correct answer for question')]
    public string $goldAnswer;

    #[InputField]
    #[Description('predicted answer for question')]
    public string $predictedAnswer;

    #[OutputField]
    #[Description('True or False')]
    public bool $isCorrect;
}


//
//// optimizes the prompt using specified strategies
//class Optimizer {
//    public string $prompt;
//    /** @var string[] */
//    public array $candidates;
//    /** @property Example[] */
//    public array $examples;
//
//    public function optimize(PredictTask $task, array $examples) : string {
//        // take starting prompt
//
//        // add examples
//        // generate improved prompt
//    }
//
//    private function examples() : string {
//        return json_encode([
//            Example::fromText('Berlin', ['country' => 'Germany']),
//        ]);
//    }
//}
//
//// evaluates the quality of the prompt
//class Evaluator {
//    public string $prompt;
//    /** @var string[] */
//    public array $candidates;
//    /** @property Example[] */
//    public array $examples;
//
//    public function __construct(PredictTask $task) : float {
//        // take prompt
//        // evaluate the metric for each example
//        // return the average metric
//    }
//
//    public function evaluate(array $examples) : float {
//        // take prompt
//        // evaluate the metric for each example
//        // return the average metric
//    }
//
//    protected function metric(array $inputs, Example $expected) : float {
//        // execute the prompt for inputs
//        // evaluate result
//    }
//}
//
//// generates synthetic data based on the structure of the input
//class Synthesizer {
//}
