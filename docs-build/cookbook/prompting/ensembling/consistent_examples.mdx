<?php
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
