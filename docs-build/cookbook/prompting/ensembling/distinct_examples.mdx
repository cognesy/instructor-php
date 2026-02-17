---
title: 'Use Distinct Example Subsets'
docname: 'distinct_examples'
id: '3eef'
---
## Overview

Demonstration Ensembling (DENSE) runs multiple prompts, each with a different subset of examples, then aggregates the outputs.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

enum Sentiment: string { case Positive='Positive'; case Negative='Negative'; case Neutral='Neutral'; }
class DemonstrationResponse { public Sentiment $correct_answer; }

class DenseEnsembling {
    public function __invoke(string $prompt, array $examples, int $numResponses) : array {
        if ($numResponses <= 0 || count($examples) % $numResponses !== 0) return [];
        $batch = intdiv(count($examples), $numResponses);
        $outputs = [];
        for ($i = 0; $i < count($examples); $i += $batch) {
            $subset = array_slice($examples, $i, $batch);
            $outputs[] = $this->one($prompt, $subset);
        }
        return $outputs;
    }

    private function one(string $prompt, array $examples) : DemonstrationResponse {
        $joined = implode("\n", $examples);
        $system = <<<TXT
        You classify queries as Positive, Negative, or Neutral.
        Refer to the provided examples. Examine each before deciding.
        Here are the examples:
        {$joined}
        TXT;
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system], ['role'=>'user','content'=>$prompt] ],
            responseModel: DemonstrationResponse::class,
            options: ['temperature' => 0.0],
        )->get();
    }
}

$userQuery = 'What is the weather like today?';
$examples = [
    'I love this product! [Positive]',
    'This is the worst service ever. [Negative]',
    'The movie was okay, not great but not terrible. [Neutral]',
    "I'm so happy with my new phone! [Positive]",
    'The food was terrible and the service was slow. [Negative]',
    "It's an average day, nothing special. [Neutral]",
    'Fantastic experience, will come again! [Positive]',
    "I wouldn't recommend this to anyone. [Negative]",
    'The book was neither good nor bad. [Neutral]',
    'Absolutely thrilled with the results! [Positive]',
];

$responses = (new DenseEnsembling)($userQuery, $examples, 5);
$counts = [];
foreach ($responses as $r) { $k = $r->correct_answer->value; $counts[$k] = ($counts[$k] ?? 0) + 1; }
arsort($counts);
$mostCommon = array_key_first($counts);
dump($mostCommon);
?>
```

### References

1) Exploring Demonstration Ensembling for In-Context Learning (https://arxiv.org/pdf/2308.08780)
