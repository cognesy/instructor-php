---
title: 'Improve With Feedback'
docname: 'improve_with_feedback'
---

## Overview

Self-Refine iteratively generates an answer, critiques it, and refines it until a stopping condition is met.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class Response { public string $code; }

class Feedback {
    #[Description('Actions to improve the code')]
    public array $feedback;
    public bool $done;
}

class Timestep {
    public string $response;
    public array $feedback;
    public string $refined_response;
}

class History {
    /** @var Timestep[] */
    public array $history = [];
    public function add(string $code, array $feedback, string $refined) : void {
        $t = new Timestep();
        $t->response = $code;
        $t->feedback = $feedback;
        $t->refined_response = $refined;
        $this->history[] = $t;
    }
}

class SelfRefinePipeline {
    public function generateInitial(string $prompt) : Response {
        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: Response::class,
            messages: [ ['role' => 'user', 'content' => $prompt] ],
        )->get();
    }

    public function generateFeedback(Response $response) : Feedback {
        $msg = <<<MSG
            You are an expert Python coder.
            Provide feedback on this code. How can we make it (1) faster and (2) more readable?

            <code>
            {$response->code}
            </code>

            If the code does not need improvement, set done = True.
            MSG;
        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: Feedback::class,
            messages: [ ['role' => 'user', 'content' => $msg] ],
        )->get();
    }

    public function refine(Response $response, Feedback $feedback) : Response {
        $feedbackLines = array_map(
            fn($item) => is_string($item) ? $item : json_encode($item),
            $feedback->feedback,
        );
        $feedbackText = implode("\n", $feedbackLines);

        $msg = <<<MSG
            You are an expert Python coder.

            <response>
            {$response->code}
            </response>

            <feedback>
            {$feedbackText}
            </feedback>

            Refine your response.
            MSG;
        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: Response::class,
            messages: [ ['role' => 'user', 'content' => $msg] ],
            )->get();
    }

    public function stop(Feedback $feedback, History $history) : bool {
        if ($feedback->done) { return true; }
        return count($history->history) >= 3;
    }
}

$pipeline = new SelfRefinePipeline();
$response = $pipeline->generateInitial('Write Python code to calculate the Fibonacci sequence.');
$history = new History();

while (true) {
    $fb = $pipeline->generateFeedback($response);
    if ($pipeline->stop($fb, $history)) { break; }
    $refined = $pipeline->refine($response, $fb);
    $history->add($response->code, $fb->feedback, $refined->code);
    $response = $refined;
}

dump($history, $response);
?>
```

## References

1. Self-Refine: Iterative Refinement with Self-Feedback (https://arxiv.org/abs/2303.17651)
2. The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)
