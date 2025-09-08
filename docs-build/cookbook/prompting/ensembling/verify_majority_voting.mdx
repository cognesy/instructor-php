---
title: 'Verify Responses over Majority Voting'
docname: 'verify_majority_voting'
---

## Overview

Diverse verifier scoring aggregates quality over unique answers, improving over majority vote.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class ResponseItem { public string $chain_of_thought; public int $answer; }
enum Grade: string { case Poor='Poor'; case Average='Average'; case Good='Good'; case Excellent='Excellent'; }
class Grading { public Grade $grade; }

class DiverseVerifier {
    public function __invoke(string $query, array $examples, int $k = 6) : int {
        $responses = [];
        for ($i = 0; $i < $k; $i++) { $responses[] = $this->generate($query, $examples); }
        $scores = [];
        foreach ($responses as $r) {
            $g = $this->score($query, $r);
            $scores[$r->answer] = ($scores[$r->answer] ?? 0.0) + $this->map($g);
        }
        arsort($scores);
        return (int) array_key_first($scores);
    }

    private function generate(string $query, array $examples) : ResponseItem {
        $formatted = implode("\n", $examples);
        $content = "You answer succinctly.\n<query>\n{$query}\n</query>\n\n<examples>\n{$formatted}\n</examples>";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user','content'=>$content] ],
            responseModel: ResponseItem::class,
        )->get();
    }

    private function score(string $query, ResponseItem $response) : Grading {
        $content = "Score the response to the query. Output only the grade.\n<query>\n{$query}\n</query>\n<response>\nChain: {$response->chain_of_thought}\nAnswer: {$response->answer}\n</response>";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user','content'=>$content] ],
            responseModel: Grading::class,
        )->get();
    }

    private function map(Grading $g) : float {
        return match($g->grade) { Grade::Excellent => 1.0, Grade::Good => 0.75, Grade::Average => 0.5, Grade::Poor => 0.25 };
    }
}

$examples = [
    "Q: James runs 3 sprints, 3 times a week, 60m each. How many meters per week? A: ... The answer is 540.",
    "Q: Brandon's iPhone age puzzle... A: ... The answer is 8.",
    "Q: Jean has 30 lollipops ... bags? A: ... The answer is 14.",
    "Q: Weng earns $12/hour, worked 50 minutes. How much? A: ... The answer is 10.",
];
$query = 'Betty needs $100; has half; parents give $15; grandparents twice parents. How much more needed?';

$best = (new DiverseVerifier)($query, $examples, k: 6);
dump($best);
?>
```

### References

1) Making Language Models Better Reasoners with Step-Aware Verifier (https://aclanthology.org/2023.acl-long.291/)
