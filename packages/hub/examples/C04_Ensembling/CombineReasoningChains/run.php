---
title: 'Combine Multiple Reasoning Chains'
docname: 'combine_reasoning_chains'
---

## Overview

Meta Chain-of-Thought (Meta-CoT) decomposes a query into sub-queries, solves each with its own reasoning chain, then composes a final answer from those chains.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ReasoningAndResponse {
    public string $intermediate_reasoning;
    public string $correct_answer;
}

class MaybeResponse {
    public ?ReasoningAndResponse $result = null;
    public ?bool $error = null;
    public ?string $error_message = null;
}

class QueryDecomposition { /** @var string[] */ public array $queries; }

class MetaCOT {
    public function __invoke(string $query) : ReasoningAndResponse {
        $subs = $this->decompose($query)->queries;
        $chains = [];
        foreach ($subs as $q) { $chains[] = $this->chain($q); }
        return $this->final($query, $chains);
    }

    public function decompose(string $query) : QueryDecomposition {
        return (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => 'Decompose the user query into minimal sub-queries needed to derive the answer.'],
                ['role' => 'user', 'content' => $query],
            ],
            responseModel: QueryDecomposition::class,
        )->get();
    }

    public function chain(string $query) : MaybeResponse {
        $system = <<<TXT
        Given a question, answer step-by-step.
        Provide intermediate reasoning and the final answer.
        If impossible, set error=true and include an error_message.
        TXT;
        return (new StructuredOutput)->with(
            messages: [ ['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $query] ],
            responseModel: MaybeResponse::class,
        )->get();
    }

    public function final(string $query, array $context) : ReasoningAndResponse {
        $parts = [];
        foreach ($context as $c) {
            if ($c instanceof MaybeResponse && !$c->error && $c->result) {
                $parts[] = $c->result->intermediate_reasoning . "\n" . $c->result->correct_answer;
            }
        }
        $formatted = implode("\n", $parts);
        $system = <<<TXT
        Given a question and context, answer step-by-step.
        If unsure, answer "Unknown".
        TXT;
        $prompt = <<<PR
        <question>
        {$query}
        </question>
        <context>
        {$formatted}
        </context>
        PR;
        return (new StructuredOutput)->with(
            messages: [ ['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt] ],
            responseModel: ReasoningAndResponse::class,
        )->get();
    }
}

$query = "Would Arnold Schwarzenegger have been able to deadlift an adult Black rhinoceros at his peak strength?";
$result = (new MetaCOT)($query);
dump($result);
?>
```

### References

1) Answering Questions by Meta-Reasoning over Multiple Chains of Thought (https://arxiv.org/pdf/2304.13007)

