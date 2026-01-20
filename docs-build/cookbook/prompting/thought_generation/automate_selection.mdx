---
title: 'Automate Example Selection'
docname: 'automate_selection'
---

## Overview

Few-shot CoT requires curated examples. We can automate selection by clustering candidate questions via embeddings, sampling per cluster, and filtering using a simple criterion (e.g., ≤ 5 reasoning steps).

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Embeddings\Embeddings;

class ExampleItem {
    public string $question;
    /** @var string[] */
    public array $reasoning_steps;
}

class AutomateSelection {
    public function __construct(private int $clusters = 2) {}

    public function __invoke(array $questions) : array {
        if ($questions === []) return [];
        $vectors = $this->embed($questions);
        [$seeds, $clusters] = $this->clusterAssign($vectors, $this->clusters);
        return $this->selectPerCluster($clusters, $questions);
    }

    private function embed(array $inputs) : array {
        $resp = (new Embeddings)
            ->using('openai')
            ->withInputs($inputs)
            ->get();
        return $resp->toValuesArray();
    }

    private function clusterAssign(array $vectors, int $k) : array {
        $n = count($vectors);
        if ($n === 0) return [[], []];
        $k = max(1, min($k, $n));
        $seeds = [$this->argMaxNorm($vectors)];
        while (count($seeds) < $k) {
            $seeds[] = $this->farthestIndex($vectors, $seeds);
        }
        $clusters = array_fill(0, count($seeds), []);
        for ($i = 0; $i < $n; $i++) {
            $si = $this->nearestSeed($vectors[$i], $vectors, $seeds);
            $dist = $this->l2($vectors[$i], $vectors[$seeds[$si]]);
            $clusters[$si][] = [$dist, $i];
        }
        foreach ($clusters as &$c) usort($c, fn($a,$b) => $a[0] <=> $b[0]);
        return [$seeds, $clusters];
    }

    private function argMaxNorm(array $vecs) : int {
        $imax = 0; $best = -INF; $i = 0;
        foreach ($vecs as $v) { $n = $this->l2($v, array_fill(0, count($v), 0.0)); if ($n > $best) { $best = $n; $imax = $i; } $i++; }
        return $imax;
    }

    private function farthestIndex(array $vecs, array $seeds) : int {
        $imax = 0; $best = -INF;
        foreach ($vecs as $i => $v) {
            if (in_array($i, $seeds, true)) continue;
            $minDist = INF;
            foreach ($seeds as $s) { $d = $this->l2($v, $vecs[$s]); if ($d < $minDist) $minDist = $d; }
            if ($minDist > $best) { $best = $minDist; $imax = $i; }
        }
        return $imax;
    }

    private function nearestSeed(array $v, array $vecs, array $seeds) : int {
        $jmin = 0; $best = INF; $j = 0;
        foreach ($seeds as $s) { $d = $this->l2($v, $vecs[$s]); if ($d < $best) { $best = $d; $jmin = $j; } $j++; }
        return $jmin;
    }

    private function l2(array $a, array $b) : float {
        $sum = 0.0; $n = count($a);
        for ($i = 0; $i < $n; $i++) { $d = ($a[$i] ?? 0.0) - ($b[$i] ?? 0.0); $sum += $d*$d; }
        return sqrt($sum);
    }

    private function generateSteps(string $question) : ?ExampleItem {
        $resp = (new StructuredOutput)->with(
            messages: [
                ['role' => 'system', 'content' => 'You are an AI assistant that generates step-by-step reasoning for mathematical questions.'],
                ['role' => 'user',   'content' => "Q: {$question}\nA: Let's think step by step."],
            ],
            responseModel: ExampleItem::class,
        )->get();
        if (count($resp->reasoning_steps) > 5) return null; // selection criterion
        return $resp;
    }

    private function selectPerCluster(array $clusters, array $questions) : array {
        $selected = [];
        foreach ($clusters as $cluster) {
            foreach ($cluster as [, $qi]) { // sorted by distance to center
                $item = $this->generateSteps($questions[$qi]);
                if ($item !== null) { $selected[] = $item; break; }
            }
        }
        return $selected;
    }
}

$questions = [
    'How many apples are left if you have 10 apples and eat 3?',
    "What's the sum of 5 and 7?",
    'If you have 15 candies and give 6 to your friend, how many do you have left?',
    "What's 8 plus 4?",
    'You start with 20 stickers and use 8. How many stickers remain?',
    'Calculate 6 added to 9.',
];

$selector = new AutomateSelection(clusters: 2);
$selected = $selector($questions);

// Selected examples per cluster, each with limited reasoning steps
dump($selected);
?>
```

### References

1) Automatic Chain of Thought Prompting in Large Language Models (https://arxiv.org/abs/2210.03493)
2) The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)

