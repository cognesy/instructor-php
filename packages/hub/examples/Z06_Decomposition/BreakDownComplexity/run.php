---
title: 'Break Down Complex Tasks'
docname: 'break_down_complexity'
---

## Overview

How can we help LLMs handle complex tasks more effectively?

Decomposed Prompting leverages a Language Model (LLM) to deconstruct a complex task into a series of manageable sub-tasks. Each sub-task is then processed by specific functions, enabling the LLM to handle intricate problems more effectively and systematically.

This approach breaks down complexity by:
- Generating an action plan using the LLM
- Executing each step systematically
- Using specific operations like Split, StrPos, and Merge

## Example

```php
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
                ActionType::Split => $current = (new Split((string) $action->parameter))->execute($this->normalizeString($current)),
                ActionType::StrPos => $current = (new StrPos((int) $action->parameter))->execute($this->normalizeStringArray($current)),
                ActionType::Merge => $current = (new Merge((string) $action->parameter))->execute($this->normalizeStringArray($current)),
            };
        }

        return $this->normalizeString($current);
    }

    private function normalizeString(mixed $value): string {
        return match (true) {
            is_string($value) => $value,
            is_array($value) => implode('', array_map(static fn(mixed $item): string => (string) $item, $value)),
            default => (string) $value,
        };
    }

    private function normalizeStringArray(mixed $value): array {
        return match (true) {
            is_array($value) => array_map(static fn(mixed $item): string => (string) $item, $value),
            default => [$this->normalizeString($value)],
        };
    }
}

$result = (new DecomposedTaskSolver)('Concatenate the second letter of every word in "Jack Ryan" together');

dump($result);
?>
```

## References

1. [Decomposed Prompting: A Modular Approach for Solving Complex Tasks](https://arxiv.org/pdf/2210.02406)
