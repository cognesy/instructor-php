---
title: 'Independently Verify Responses'
docname: 'verify_independently'
---

## Overview

Chain-of-Verification (CoVe) verifies an answer by generating validation questions, answering them independently, and judging the original answer.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class QueryResponse { public string $correct_answer; }

class ValidationQuestions {
    #[Description('Questions to validate the response')]
    public array $question;
}

class ValidationAnswer { public string $answer; }

class FinalResponse { public string $correct_answer; }

class CoVeVerifier {
    public function run(string $query) : FinalResponse {
        $initial = $this->generateInitialResponse($query);
        $questions = $this->generateVerificationQuestions($initial->correct_answer);
        $answers = $this->generateVerificationResponses($questions->question);
        return $this->generateFinalResponse($answers, $initial, $query);
    }

    private function generateInitialResponse(string $query) : QueryResponse {
        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: QueryResponse::class,
            messages: [
                ['role' => 'system', 'content' => 'You are an expert question answering system'],
                ['role' => 'user', 'content' => $query],
            ],
        )->get();
    }

    private function generateVerificationQuestions(string $llmResponse) : ValidationQuestions {
        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: ValidationQuestions::class,
            messages: [
                ['role' => 'system', 'content' => 'You generate follow-up questions to validate a response. Focus on key assumptions and facts.'],
                ['role' => 'user', 'content' => $llmResponse],
            ],
        )->get();
    }

    private function generateVerificationResponses(array $questions) : array {
        $pairs = [];
        foreach ($questions as $q) {
            $ans = (new StructuredOutput)->with(
                model: 'gpt-5-nano',
                responseModel: ValidationAnswer::class,
                messages: [
                    ['role' => 'system', 'content' => 'You answer validation questions precisely.'],
                    ['role' => 'user', 'content' => $q],
                ],
            )->get();
            $pairs[] = [$ans, $q];
        }
        return $pairs;
    }

    private function generateFinalResponse(array $answers, QueryResponse $initial, string $originalQuery) : FinalResponse {
        $formatted = [];
        foreach ($answers as [$ans, $q]) { $formatted[] = "Q: {$q}\nA: {$ans->answer}"; }
        $joined = implode("\n", $formatted);

        return (new StructuredOutput)->with(
            model: 'gpt-5-nano',
            responseModel: FinalResponse::class,
            messages: [
                ['role' => 'system', 'content' => 'Validate whether the initial answer answers the initial query given Q/A evidence. Return the original if valid; otherwise provide a corrected answer.'],
                ['role' => 'user', 'content' => "Initial query: {$originalQuery}\nInitial Answer: {$initial->correct_answer}\nVerification Questions and Answers:\n{$joined}"],
            ],
        )->get();
    }
}

$query = 'What was the primary cause of the Mexican-American War and how long did it last?';
$final = (new CoVeVerifier)->run($query);
dump($final);
?>
```

## References

1. Chain-Of-Verification Reduces Hallucination In Large Language Models (https://arxiv.org/pdf/2309.11495)
