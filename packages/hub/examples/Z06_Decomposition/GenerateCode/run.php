---
title: 'Generate Code for Intermediate Steps'
docname: 'generate_code'
---

## Overview

How can we leverage external code execution to generate intermediate reasoning steps?

Program of Thought aims to leverage an external code interpreter to generate intermediate reasoning steps. This helps achieve greater performance in mathematical and programming-related tasks by grounding our final response in deterministic code.

The approach involves:
1. **Generate Code**: Create a solver function that implements step-by-step logic
2. **Execute Code**: Run the generated code to get deterministic results
3. **Extract Answer**: Use the computed result to make final predictions

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

enum Choice: string {
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
}

class Prediction {
    public function __construct(
        public Choice $choice
    ) {}
}

class ProgramExecution {
    public function __construct(
        public string $program_code
    ) {}
}

class ProgramOfThoughtSolver {
    private const PREFIX = '\<?php
// Answer this question by implementing a solver()
// function, use for loop if necessary.
function solver() {
    // Let\'s write a PHP program step by step,
    // and then return the answer
    // Firstly, we need to define the following
    // variables:';
    
    public function __invoke(string $query, array $options): string {
        $reasoning = $this->generateIntermediateReasoning($query);
        $answer = $this->executeProgram($reasoning->program_code);
        $prediction = $this->generatePrediction($answer, $options, $query);
        return $prediction->choice->value;
    }
    
    private function generateIntermediateReasoning(string $query): ProgramExecution {
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'system',
                    'content' => 'You are a world class AI system that excels at answering user queries in a systematic and detailed manner. Generate a valid PHP program that can be executed to answer the user query.

Make sure to begin your generated program with the following structure:
```php
<?php
function solver() {
    // Your step-by-step logic here
    // Define variables, perform calculations
    // Return the final answer
}
return solver();
```'
                ],
                ['role' => 'user', 'content' => $query],
            ],
            responseModel: ProgramExecution::class,
        )->get();
    }
    
    private function executeProgram(string $code): mixed {
        try {
            $sanitized = $this->sanitizeCode($code);
            // Ensure we get a value even if the model forgot to return it explicitly
            if (strpos($sanitized, 'return') === false) {
                $sanitized .= "\nreturn (function(){ return function_exists('solver') ? solver() : null; })();";
            }
            return eval($sanitized);
        } catch (Throwable $e) {
            throw new Exception("Program execution failed: " . $e->getMessage());
        }
    }

    private function sanitizeCode(string $code): string {
        // Strip Markdown fences
        $code = preg_replace('/^\s*```[a-zA-Z]*\s*/', '', $code);
        $code = preg_replace('/```\s*$/', '', (string) $code);
        // Remove PHP open/close tags — eval() expects pure PHP code body
        $code = str_replace(['<?php', '<?', '?>'], '', (string) $code);
        // Normalize line endings and trim
        $code = trim((string) $code);
        return $code;
    }
    
    private function generatePrediction(mixed $predictedAnswer, array $options, string $query): Prediction {
        $formattedOptions = implode(', ', $options);
        
        return (new StructuredOutput)->with(
            messages: [
                [
                    'role' => 'system',
                    'content' => "Find the closest option based on the question and prediction.

Question: {$query}
Prediction: {$predictedAnswer}
Options: [{$formattedOptions}]"
                ],
            ],
            responseModel: Prediction::class,
        )->get();
    }
}

$result = (new ProgramOfThoughtSolver)(
    "A trader sold an article at a profit of 20% for Rs.360. What is the cost price of the article?",
    ["A)270", "B)300", "C)280", "D)320", "E)315"]
);

dump($result);
?>
```

## References

1. [Program of Thoughts Prompting: Disentangling Computation from Reasoning for Numerical Reasoning Tasks](https://arxiv.org/abs/2211.12588)
