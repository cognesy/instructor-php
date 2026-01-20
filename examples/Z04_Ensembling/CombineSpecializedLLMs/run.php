---
title: 'Combine Different Specialized LLMs'
docname: 'combine_specialized_llms'
---

## Overview

Mixture of Reasoning Experts (MoRE) combines specialized experts (e.g., factual with evidence, and multi-hop reasoning) and selects the best answer using a scorer.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class MultihopExpert { public string $chain_of_thought; public string $answer; }
class FactualExpert { public string $answer; }
class ModelScore { public float $score; }

class MoRE {
    public function factual(string $query, array $evidences) : FactualExpert {
        $formatted = '- ' . implode("\n-", $evidences);
        $system = "<query>\n{$query}\n</query>\n\n<evidences>\n{$formatted}\n</evidences>";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system] ],
            responseModel: FactualExpert::class,
        )->get();
    }

    public function multihop(string $query) : MultihopExpert {
        $system = "<query>\n{$query}\n</query>";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system] ],
            responseModel: MultihopExpert::class,
        )->get();
    }

    public function score(string $query, string $answer) : ModelScore {
        $messages = [
            ['role'=>'system','content'=>'You score answers by how well they answer the user query (0..1).'],
            ['role'=>'user','content'=>"<user query>\n{$query}\n</user query>\n\n<response>\n{$answer}\n</response>"],
        ];
        return (new StructuredOutput)->with(
            messages: $messages,
            responseModel: ModelScore::class,
        )->get();
    }
}

$query = "Who's the original singer of Help Me Make It Through The Night?";
$evidences = ["Help Me Make It Through The Night is a country music ballad written and composed by Kris Kristofferson and released on his 1970 album 'Kristofferson'"];
$threshold = 0.8;

$more = new MoRE();
$factual = $more->factual($query, $evidences);
$multihop = $more->multihop($query);

$fScore = $more->score($query, $factual->answer)->score ?? 0.0;
$mScore = $more->score($query, $multihop->answer)->score ?? 0.0;

$answer = '';
if (max($fScore, $mScore) < $threshold) {
    $answer = 'Abstaining from responding';
} else {
    $answer = ($fScore > $mScore) ? $factual->answer : $multihop->answer;
}

dump($answer);
?>
```

### References

1) Getting MoRE out of Mixture of Language Model Reasoning Experts (https://arxiv.org/pdf/2305.14628)
