---
title: 'Break Down Complex Tasks'
docname: 'break_down_complexity'
id: 'dffa'
tags:
  - 'decomposition'
  - 'task-breakdown'
  - 'reasoning'
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
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

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
    public int $id;
    public ActionType $type;
    public string|int $parameter;
}

class ActionPlan implements CanProvideJsonSchema {
    public string $initial_data = '';
    /** @var Action[] */
    public array $plan = [];

    public function toJsonSchema() : array {
        return [
            'type' => 'object',
            'x-title' => 'ActionPlan',
            'x-php-class' => self::class,
            'properties' => [
                'initial_data' => ['type' => 'string'],
                'plan' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'x-title' => 'Action',
                        'x-php-class' => Action::class,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'type' => [
                                'type' => 'string',
                                'enum' => array_map(
                                    static fn(ActionType $type): string => $type->value,
                                    ActionType::cases(),
                                ),
                                'x-php-class' => ActionType::class,
                            ],
                            'parameter' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'integer'],
                                ],
                            ],
                        ],
                        'required' => ['id', 'type', 'parameter'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['initial_data', 'plan'],
            'additionalProperties' => false,
        ];
    }
}

class DecomposedTaskSolver {
    public function __invoke(string $taskDescription): string {
        $plan = $this->deriveActionPlan($taskDescription);
        return $this->executePlan($plan);
    }
    
    private function deriveActionPlan(string $taskDescription): ActionPlan {
        return StructuredOutput::using('openai')->with(
            messages: [
                ['role' => 'system', 'content' => 'Generate an action plan to solve the task. Set initial_data to the exact input string from the task. Available actions: Split (split string by character into array), StrPos (get character at given index from each string in array), Merge (join array of strings with character). Example: for "get first letter of each word in \'Hi Bob\'", set initial_data="Hi Bob", then Split(" "), StrPos(0), Merge("").'],
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

assert(is_string($result));
assert(!empty($result));
?>
```

## References

1. [Decomposed Prompting: A Modular Approach for Solving Complex Tasks](https://arxiv.org/pdf/2210.02406)
