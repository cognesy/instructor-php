---
title: 'Use Ensembles To Test Prompts'
docname: 'ensemble_test_prompts'
---

## Overview

### What's Max Mutual Information?
Max Mutual Information Method aims to find the best prompt to elicit the desired response from an LLM by maximizing a mutual information proxy — i.e., reducing model uncertainty with the prompt.

### Entropy
When a language model receives a prompt, it produces a distribution over outputs. Lower entropy suggests higher confidence.

### Mutual Information
We approximate mutual information as the difference between marginal and conditional entropies of outputs across multiple samples for a given prompt. Below, we use a lightweight proxy based on answer diversity and rationale repetitiveness.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class CoT { /** @var string[] */ public array $chain_of_thought; public int $answer; }
class PromptScore { public string $prompt; public float $score; }

class PromptEnsembler {
    public function __construct(private int $k = 3) {}
    public function evaluate(array $prompts, string $question) : array {
        $scored = [];
        foreach ($prompts as $p) { $scored[] = $this->scorePrompt($p, $question); }
        usort($scored, fn($a,$b)=> $a->score <=> $b->score);
        return $scored; // lower is better (proxy for MI)
    }
    private function scorePrompt(string $prompt, string $question) : PromptScore {
        $answers = [];
        for ($i = 0; $i < $this->k; $i++) { $answers[] = $this->run("{$prompt}\n\n{$question}"); }
        $entropy = $this->normalizedEntropy(array_map(fn($r)=>$r->answer, $answers));
        $rep = $this->repetitiveness($answers);
        $ps = new PromptScore(); $ps->prompt = $prompt; $ps->score = $entropy - $rep; return $ps;
    }
    private function run(string $input) : CoT {
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user','content'=>$input] ],
            responseModel: CoT::class,
        )->get();
    }
    private function normalizedEntropy(array $answers) : float {
        $n = count($answers); if ($n===0) return 0.0; $freq=[]; foreach($answers as $a){$freq[$a]=($freq[$a]??0)+1;}
        $h=0.0; foreach($freq as $c){$p=$c/$n; $h += ($p>0)? -$p*log($p):0.0;} $max=log(max(1,count($freq))); return $max>0? $h/$max:0.0;
    }
    private function repetitiveness(array $responses) : float {
        $r = array_map(fn($x)=> implode(' ', $x->chain_of_thought), $responses);
        $uniq = count(array_unique($r)); $n = max(1,count($responses)); return 1.0 - ($uniq/$n);
    }
}

$prompts = [
    'Explain step-by-step then answer:',
    'Think carefully and provide reasoning before the answer:',
    'Reason in numbered steps and conclude with the final number:',
];
$question = 'If a store sold 93 in the morning and 39 in the afternoon from 200 baked, and 6 were returned, how many remain?';
$scores = (new PromptEnsembler)->evaluate($prompts, $question);
dump($scores);
?>
```

### References

1) https://learnprompting.org/docs/advanced/ensembling/max_mutual_information_method

