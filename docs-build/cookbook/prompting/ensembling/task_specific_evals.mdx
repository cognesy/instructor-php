<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

enum Emotion: string { case Happy='Happy'; case Angry='Angry'; case Sadness='Sadness'; }
enum Confidence: string { case Uncertain='Uncertain'; case Somewhat='Somewhat Confident'; case Confident='Confident'; case Highly='Highly Confident'; }

class Classification { public string $chain_of_thought; public Emotion $label; public Confidence $confidence; }

class USPClassification {
    public function classify(string $query) : Classification {
        $content = 'Classify the following query into one of: Happy, Angry, Sadness';
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$content], ['role'=>'user','content'=>$query] ],
            responseModel: Classification::class,
        )->get();
    }

    public function balancedSample(array $queries, int $k) : array {
        $preds = [];
        foreach ($queries as $q) { $preds[] = [$this->classify($q), $q]; }
        $by = [];
        foreach ($preds as $p) { $by[$p[0]->label->value][] = $p; }
        $per = max(1, intdiv($k, max(1, count($by))));
        $out = [];
        foreach ($by as $label => $items) {
            usort($items, fn($a,$b) => $this->score($b[0]->confidence) <=> $this->score($a[0]->confidence));
            $slice = array_slice($items, 0, $per);
            foreach ($slice as $it) { $out[] = $it[1] . " ({$label})"; }
        }
        return $out;
    }

    public function finalWithExamples(string $query, array $examples) : Classification {
        $formatted = implode("\n", $examples);
        $system = "You classify queries into Happy, Angry, or Sadness.\n<examples>\n{$formatted}\n</examples>";
        return (new StructuredOutput)->with(
            messages: [ ['role'=>'system','content'=>$system], ['role'=>'user','content'=>$query] ],
            responseModel: Classification::class,
        )->get();
    }

    private function score(Confidence $c) : int {
        return match($c) { Confidence::Highly => 4, Confidence::Confident => 3, Confidence::Somewhat => 2, Confidence::Uncertain => 1 };
    }
}
?>
```
```php
<?php
require 'examples/boot.php';
$examples = [
        "i do feel that running is a divine experience and
        that i can expect to have some type of spiritual
        encounter",
        "i get giddy over feeling elegant in a perfectly
        fitted pencil skirt",
        "
        i plan to share my everyday life stories traveling
        adventures inspirations and handmade creations with
        you and hope you will also feel inspired
        ",
        "
        i need to feel the dough to make sure its just
        perfect
        ",
        "
        i found myself feeling a little discouraged that
        morning
        ",
        "i didnt really feel that embarrassed",
        "i feel like a miserable piece of garbage",
        "
        i feel like throwing away the shitty piece of shit
        paper
        ",
        "
        i feel irritated and rejected without anyone doing
        anything or saying anything
        ",
        "i feel angered and firey",
        "
        im feeling bitter today my mood has been strange the
        entire day so i guess its that
        ",
        "i just feel really violent right now",
        "i know there are days in which you feel distracted",
    ];

// Run the selection and final classification
$usp = new USPClassification();
$balanced = $usp->balancedSample($examples, 3);
$final = $usp->finalWithExamples('i feel furious that right to life advocates can and do tell me how to live and die', $balanced);
dump($balanced, $final);
?>
