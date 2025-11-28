---
title: 'Determine Uncertainty of Reasoning Chain'
docname: 'determine_uncertainty'
---

## Overview

We want models to assess confidence in their own answers. Self-Calibration asks the model to justify an answer and state whether it is valid.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class SelfCalibration {
    #[Description('Reasoning about answer validity')]
    public string $chain_of_thought;
    #[Description('Whether the answer is correct or not')]
    public bool $is_valid_answer;
}

class EvaluateModelOutput {
    public function __invoke(string $originalPrompt, string $modelResponse) : SelfCalibration {
        $messages = <<<MSG
            Question: {$originalPrompt}

            {$modelResponse}

            Is this a valid answer to the question?
            Examine the question thoroughly and generate a complete
            reasoning for why the answer is correct or not before responding.
            MSG;

        return (new StructuredOutput)->with(
            messages: $messages,
            responseModel: SelfCalibration::class,
            model: 'gpt-5-nano',
        )->get();
    }
}

$originalPrompt = <<<PROMPT
Who was the third president of the United States?
PROMPT;

$modelResponse = <<<MODEL
Here are some brainstormed ideas:
James Monroe
Thomas Jefferson
Jefferson
Thomas Jefferson
George Washington
MODEL;

$result = (new EvaluateModelOutput)($originalPrompt, $modelResponse);
dump($result);
?>
```

## References

1. Language Models (Mostly) Know What They Know (https://arxiv.org/pdf/2207.05221)
