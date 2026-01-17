---
title: 'Break Down Reasoning Into Multiple Steps'
docname: 'break_down_reasoning'
---

## Overview

Cumulative Reasoning separates reasoning into propose → verify → report for better logical inference.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

enum Prediction : string { case False = 'False'; case True = 'True'; case Unknown = 'Unknown'; }

class Proposition {
    public string $premise1;
    public string $premise2;
    public string $reasoning;
    public string $proposition;
}

class ProposerOutput {
    public string $reasoning;
    #[Description('Deduced propositions relevant to the hypothesis')]
    public array $valid_propositions; // of Proposition
    public Prediction $prediction;
}

class VerifiedProposition {
    public string $proposition;
    public string $reasoning;
    public bool $is_valid;
}

class ReporterOutput {
    public string $reasoning;
    public bool $is_valid_hypothesis;
}

class CumulativeReasoningPipeline {
    public function propose(array $premises, string $hypothesis) : ProposerOutput {
        $formatted = '- ' . implode("\n- ", $premises);
        $sys = <<<SYS
            Think step by step using FOL to deduce propositions from given premises (at most two per deduction).
            Avoid duplicating premises; reason only from stated premises/propositions.
            SYS;
        $user = <<<USR
            Premises:
            {$formatted}

            We want to deduce more Propositions to determine correctness of the following Hypothesis:
            Hypothesis: {$hypothesis}
            USR;
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini',
            responseModel: ProposerOutput::class,
            messages: [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $user],
            ],
        )->get();
    }

    public function verify(ProposerOutput $proposal) : array {
        $results = [];
        foreach ($proposal->valid_propositions as $p) {
            $sys = 'Use FOL to determine whether the deduction from two premises to the proposition is valid.';
            $user = "Premises:\n{$p->premise1}\n{$p->premise2}\n\nProposition:\n{$p->proposition}";
            $res = (new StructuredOutput)->with(
                model: 'gpt-4o-mini',
                responseModel: VerifiedProposition::class,
                messages: [ ['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user] ],
            )->get();
            $results[] = $res;
        }
        return $results;
    }

    public function report(array $verificationResult, string $hypothesis, array $premises) : ReporterOutput {
        $formattedPrem = '- ' . implode("\n- ", $premises);
        $props = [];
        foreach ($verificationResult as $v) { if ($v->is_valid) { $props[] = $v->proposition; } }
        $formattedProp = '- ' . implode("\n- ", $props);
        $sys = <<<SYS
            Think step by step. Read and analyze the Premises, then use FOL to judge whether the Hypothesis is True, False, or Unknown using the verified Propositions.
            SYS;
        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => "Premises:\n{$formattedPrem}\n\nHypothesis: {$hypothesis}"],
            ['role' => 'assistant', 'content' => "Let's think step by step. From the premises, we can deduce the following propositions:\n{$formattedProp}\n\nRecall the Hypothesis: {$hypothesis}"],
        ];
        return (new StructuredOutput)->with(
            model: 'gpt-4o-mini', responseModel: ReporterOutput::class, messages: $messages,
        )->get();
    }
}

$hypothesis = 'Hyraxes lay eggs';
$premises = [
    'The only types of mammals that lay eggs are platypuses and echidnas',
    'Platypuses are not hyrax',
    'Echidnas are not hyrax',
    'No mammals are invertebrates',
    'All animals are either vertebrates or invertebrates',
    'Mammals are animals',
    'Hyraxes are mammals',
    'Grebes lay eggs',
    'Grebes are not platypuses and also not echidnas',
];

$pipeline = new CumulativeReasoningPipeline();
$proposal = $pipeline->propose($premises, $hypothesis);
$verified = $pipeline->verify($proposal);
$report = $pipeline->report($verified, $hypothesis, $premises);

dump($proposal, $verified, $report);
?>
```

### References

1: Cumulative Reasoning with Large Language Models (https://arxiv.org/pdf/2308.04371)
