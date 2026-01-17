---
title: 'Prioritize Consistent Examples'
docname: 'consistent_examples'
---
Consistency Based Self Adaptive Prompting (COSP)1 aims to improve LLM output quality by generating high quality few shot examples to be included in the final prompt. These are examples without labelled ground truth so they use self-consistency and a metric known as normalized entropy to select the best examples.

Once they've selected the examples, they then append them to the prompt and generate multiple reasoning chains before selecting the final result using Self-Consistency.

COSP process¶


How does this look in practice? Let's dive into greater detail.

Step 1 - Selecting Examples¶
In the first step, we try to generate high quality examples from questions that don't have ground truth labels. This is challenging because we want to find a way to automatically determine answer quality when sampling our model multiple times.

In this case, we have n questions which we want to generate m possible reasoning chains for each question. This gives a total of nm examples. We then want to filter out k final few shot examples from these nm examples to be included inside our final prompt.

Using chain of thought, we first generate m responses for each question. These responses contain a final answer and a rationale behind that answer.
We compute a score for each response using a weighted sum of two values - normalized entropy and repetitiveness ( How many times this rationale appears for this amswer )
We rank all of our nm responses using this score and choose the k examples with the lowest scores as our final few shot examples.

Normalized Entropy

In the paper, the authors write that normalized entropy is a good proxy over a number of different tasks where low entropy is positively correlated with correctness. Entropy is also supposed to range from 0 to 1.

Therefore in order to do so, we introduce a - term in our implementation so that the calculated values range from 0 to 1.


```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class CoTResponse { /** @var string[] */ public array $chain_of_thought; public int $answer; }
class ScoredExample { public string $query; public CoTResponse $response; public float $score; }

class COSPSelector {
    public function __construct(private int $m = 3) {}

    public function generate(string $query) : array {
        $out = [];
        for ($i = 0; $i < $this->m; $i++) { $out[] = $this->cot($query); }
        return $out;
    }

    public function scoreExamples(string $query, array $responses) : array {
        $entropy = $this->normalizedEntropy(array_map(fn($r)=>$r->answer, $responses));
        $repetitiveness = $this->repetitiveness($responses);
        $scored = [];
        foreach ($responses as $r) {
            $s = $entropy - $repetitiveness; // lower is better
            $se = new ScoredExample(); $se->query = $query; $se->response = $r; $se->score = $s; $scored[] = $se;
        }
        return $scored;
    }

    public function select(array $candidates, int $k) : array {
        $all = [];
        foreach ($candidates as $q) { $all = array_merge($all, $this->scoreExamples($q, $this->generate($q))); }
        usort($all, fn($a,$b)=> $a->score <=> $b->score);
        return array_slice($all, 0, $k);
    }

    private function cot(string $query) : CoTResponse {
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'user','content'=>$query] ],
            responseModel: CoTResponse::class,
        )->get();
    }

    private function normalizedEntropy(array $answers) : float {
        $n = count($answers); if ($n === 0) return 0.0; $freq=[]; foreach($answers as $a){$freq[$a]=($freq[$a]??0)+1;}
        $h = 0.0; foreach ($freq as $c) { $p = $c / $n; $h += ($p > 0) ? -$p * log($p) : 0.0; }
        $maxH = log(max(1, count($freq)));
        return $maxH > 0 ? $h / $maxH : 0.0;
    }

    private function repetitiveness(array $responses) : float {
        // approximate: 1 - (unique rationales / total)
        $rationales = array_map(fn($r)=> implode(' ', $r->chain_of_thought), $responses);
        $unique = count(array_unique($rationales));
        $n = max(1, count($responses));
        return 1.0 - ($unique / $n);
    }
}

$questions = [
    'How many loaves of bread did they have left?',
    'How many pages does James write in a year?',
];
$selector = new COSPSelector(m: 3);
$best = $selector->select($questions, k: 3);
dump($best);
?>
```

### References

1: Better Zero-Shot Reasoning with Self-Adaptive Prompting (https://arxiv.org/pdf/2305.14106)
