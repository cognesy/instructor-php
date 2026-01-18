<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class ReasoningStep {
    public function __construct(
        public int $id,
        /** @var string[] */
        public array $rationale,
        /** @var int[] */
        public array $dependencies,
        public string $eval_string = ''
    ) {}
}

class TaskSpecificReasoner {
    public function __invoke(string $query): mixed {
        $steps = $this->generateReasoningSteps($query);
        return $this->executeSteps($steps);
    }
    
    private function generateReasoningSteps(string $query): array {
        return (new StructuredOutput)->with(
                messages: [
                    [
                        'role' => 'system',
                        'content' => 'You are a world class AI who excels at generating reasoning steps to answer a question. Generate a list of reasoning steps needed to answer the question.

For each reasoning step, provide:
- id: step number starting from 1
- rationale: array of strings explaining the reasoning
- dependencies: array of step IDs this step depends on
- eval_string: valid PHP code (without <?php tags) that can be evaluated

At each step you should either:
- declare a variable to be referenced later on (e.g., "$cars = 3;")
- combine multiple variables together to generate a new result (e.g., "$total = $cars + $more;")

The final step must store the answer in a variable called $answer.

Example eval_string values:
- "$cars = 3;"
- "$more_cars = 2;"
- "$answer = $cars + $more_cars;"

Use only valid PHP syntax for variable assignments in eval_string.'
                    ],
                    ['role' => 'user', 'content' => $query],
                ],
                responseModel: Sequence::of(ReasoningStep::class),
            )->get()->toArray();
    }
    
    private function executeSteps(array $steps): mixed {
        $code = [];
        foreach ($steps as $step) {
            $code[] = $step->eval_string;
        }

        $fullCode = "<?php\n" . implode("\n", $code) . "\nreturn \$answer;";

        $result = eval(substr($fullCode, 5));
        return $result;
    }
}

$result = (new TaskSpecificReasoner)(
    'If there are 3 cars in the parking lot and 2 more cars arrive, how many cars are in the parking lot after another 2 more arrive?'
);

dump($result);
?>
