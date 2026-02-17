---
title: 'Reconstruct Prompt from Reasoning Steps'
docname: 'reconstruct_prompt'
id: '931d'
---
## Overview

Reverse Chain-of-Thought (RCoT) reconstructs a likely prompt from reasoning steps, compares condition lists, and refines the answer with targeted feedback.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class ReconstructedPrompt {
    public string $chain_of_thought;
    #[Description('Reconstructed prompt that could yield the given reasoning and answer')]
    public string $reconstructed_prompt;
}

class ConditionList {
    #[Description('Key conditions relevant to answer the question')]
    public array $conditions;
}

class ModelFeedback {
    #[Description('Detected inconsistencies between original and reconstructed condition lists')]
    public array $detected_inconsistencies;
    public string $feedback;
    public bool $is_equal;
}

class ModelResponse {
    #[Description('Logical steps leading to the final statement')]
    public string $chain_of_thought;
    public string $correct_answer;
}

class RCoTPipeline {
    public function generateResponse(string $query) : ModelResponse {
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ModelResponse::class,
            messages: [
                ['role' => 'system', 'content' => "Generate logical steps before answering."],
                ['role' => 'user', 'content' => $query],
            ],
        )->get();
    }

    public function reconstruct(ModelResponse $response) : ReconstructedPrompt {
        $sys = <<<SYS
            Give a concrete prompt that could generate this answer.
            Include all necessary information and ask for one result.

            Reasoning: {$response->chain_of_thought}
            Response: {$response->correct_answer}
            SYS;
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ReconstructedPrompt::class,
            messages: [ ['role' => 'system', 'content' => $sys] ],
        )->get();
    }

    public function deconstructToConditions(string $prompt) : ConditionList {
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ConditionList::class,
            messages: [
                ['role' => 'system', 'content' => "List the key conditions required to answer the problem."],
                ['role' => 'user', 'content' => $prompt],
            ],
        )->get();
    }

    public function compareConditions(array $original, array $reconstructed) : ModelFeedback {
        $orig = "- " . implode("\n- ", $original);
        $recon = "- " . implode("\n- ", $reconstructed);
        $sys = <<<SYS
            Analyze and compare two lists of conditions.
            Original Condition List:
            {$orig}

            Reconstructed Condition List:
            {$recon}

            Determine rough equivalence. If not equivalent, provide targeted feedback.
            SYS;
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ModelFeedback::class,
            messages: [ ['role' => 'system', 'content' => $sys] ],
        )->get();
    }

    public function revise(ModelResponse $response, ModelFeedback $feedback) : ModelResponse {
        $miss = "- " . implode("\n- ", $feedback->detected_inconsistencies);
        $sys = <<<SYS
            Here are the mistakes and reasons in your answer:
            Original Response: {$response->correct_answer}
            Overlooked conditions:
            {$miss}

            Reasons:
            {$feedback->feedback}

            Generate a revised response that addresses the feedback and includes the ignored conditions.
            SYS;
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ModelResponse::class,
            messages: [ ['role' => 'system', 'content' => $sys] ],
        )->get();
    }
}

$query = <<<Q
Mary is an avid gardener. Yesterday, she received 18 new potted plants from her favorite plant nursery. She already has 2 potted plants on each of the 40 window ledges of her large backyard. How many potted plants will Mary remain with?
Q;

$pipeline = new RCoTPipeline();
$response = $pipeline->generateResponse($query);
$reconstructed = $pipeline->reconstruct($response);

$originalList = $pipeline->deconstructToConditions($query);
$reconstructedList = $pipeline->deconstructToConditions($reconstructed->reconstructed_prompt);

$feedback = $pipeline->compareConditions($originalList->conditions, $reconstructedList->conditions);
if (!$feedback->is_equal) {
    $response = $pipeline->revise($response, $feedback);
}

dump($reconstructed, $originalList, $reconstructedList, $feedback, $response);
?>
```

## References

1. RCoT: Detecting And Rectifying Factual Inconsistency In Reasoning By Reversing Chain-Of-Thought (https://arxiv.org/pdf/2305.11499)
