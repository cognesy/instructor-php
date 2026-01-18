<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

enum ActionType: string {
    case Split = 'split';
    case StrPos = 'strpos';
    case Merge = 'merge';
}

class Split {
    public function __construct(
        public string $split_char
    ) {}
    
    public function execute(string $input): array {
        return explode($this->split_char, $input);
    }
}

class StrPos {
    public function __construct(
        public int $index
    ) {}
    
    public function execute(array $input): array {
        return array_map(fn($str) => $str[$this->index] ?? '', $input);
    }
}

class Merge {
    public function __construct(
        public string $merge_char
    ) {}
    
    public function execute(array $input): string {
        return implode($this->merge_char, $input);
    }
}

class Action {
    public function __construct(
        public int $id,
        public ActionType $type,
        public string|int $parameter
    ) {}
}

class ActionPlan {
    public function __construct(
        public string $initial_data,
        /** @var Action[] */
        public array $plan
    ) {}
}

class DecomposedTaskSolver {
    public function __invoke(string $taskDescription): string {
        $plan = $this->deriveActionPlan($taskDescription);
        return $this->executePlan($plan);
    }
    
    private function deriveActionPlan(string $taskDescription): ActionPlan {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => 'Generate an action plan to help complete the task. Available actions: Split (split string by character), StrPos (get character at index from each string), Merge (join strings with character)'],
                ['role' => 'user', 'content' => $taskDescription],
            ],
            responseModel: ActionPlan::class,
        )->get();
    }
    
    private function executePlan(ActionPlan $plan): string {
        $current = $plan->initial_data;
        
        foreach ($plan->plan as $action) {
            match ($action->type) {
                ActionType::Split => $current = (new Split($action->parameter))->execute($current),
                ActionType::StrPos => $current = (new StrPos($action->parameter))->execute($current),
                ActionType::Merge => $current = (new Merge($action->parameter))->execute($current),
            };
        }
        
        return $current;
    }
}

$result = (new DecomposedTaskSolver)('Concatenate the second letter of every word in "Jack Ryan" together');

dump($result);
?>
