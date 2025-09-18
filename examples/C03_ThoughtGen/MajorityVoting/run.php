---
title: 'Use Majority Voting'
docname: 'majority_voting'
---

## Overview

Uncertainty-Routed Chain-of-Thought generates multiple chains (e.g., 8 or 32), then takes the majority answer if its proportion exceeds a threshold; otherwise, fall back to a single response.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

enum OptionLetter: string { case A='A'; case B='B'; case C='C'; case D='D'; }

class ChainOfThoughtResponse {
    public string $chain_of_thought;
    public OptionLetter $correct_answer;
}

class MajorityVoting {
    public function __invoke(string $query, array $options, int $k = 8, float $threshold = 0.6) : OptionLetter {
        $responses = [];
        for ($i = 0; $i < $k; $i++) { $responses[] = $this->generate($query, $options); }
        $counts = [];
        foreach ($responses as $r) {
            $key = $r->correct_answer->value; $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $major = array_key_first($counts);
        $prop = ($counts[$major] ?? 0) / max(1, $k);
        if ($prop < $threshold) return $this->generate($query, $options)->correct_answer;
        return OptionLetter::from($major);
    }

    private function generate(string $query, array $options) : ChainOfThoughtResponse {
        $formatted = implode("\n", array_map(fn($k,$v)=>"{$k}: {$v}", array_keys($options), $options));
        $system = <<<TXT
        You are a world-class AI for complex questions. Choose the single best option.
        <question>
        {$query}
        </question>
        <options>
        {$formatted}
        </options>
        TXT;
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system] ],
            responseModel: ChainOfThoughtResponse::class,
        )->get();
    }
}

$question = <<<Q
In a population of giraffes, an environmental change favors taller individuals. More tall giraffes obtain nutrients and survive to pass along their genes. This is an example of:
Q;

$options = [
    'A' => 'directional selection',
    'B' => 'stabilizing selection',
    'C' => 'sexual selection',
    'D' => 'disruptive selection',
];

$answer = (new MajorityVoting)($question, $options, k: 8, threshold: 0.6);
dump($answer);
?>
```

### References

1) Gemini: A Family of Highly Capable Multimodal Models (https://storage.googleapis.com/deepmind-media/gemini/gemini_1_report.pdf)
