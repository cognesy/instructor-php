---
title: 'Use Task Specific Evaluation Metrics'
docname: 'task_specific_evals'
---

## Overview
Universal Self Prompting is a two stage process similar to Consistency Based Self Adaptive Prompting (COSP). Here is a breakdown of the two stages.

Generate Examples : LLMs are prompted to generate a collection of candidate responses using a test dataset
Answer Query : We then select a few of these model-generated responses as examples to prompt the LLM to obtain a final prediction.
Note here that the final answer is obtained using a single forward pass with greedy decoding.

USP Process¶


Let's see how this works in greater detail.

Generate Few Shot Examples¶
We first prompt our model to generate responses for a given set of prompts. Instead of measuring the entropy and repetitiveness as in COSP, we use one of three possible methods to measure the quality of the generated responses. These methods are decided based on the three categories supported.

This category has to be specified by a user ahead of time.

Note that for Short Form and Long Form generation, we generate
m
m different samples. This is not the case for classification tasks.

Classification : Classification Tasks are evaluated using the normalized probability of each label using the raw logits from the LLM.

In short, we take the raw logit for each token corresponding to the label, use a softmax to normalize each of them and then sum across the individual probabilities and their log probs. We also try to sample enough queries such that we have a balanced number of predictions across each class ( so that our model doesn't have a bias towards specific classes )

Short Form Generation: This is done by using a similar formula to COSP but without the normalizing term

Long Form Generation: This is done by using the average pairwise ROUGE score between all pairs of the m responses.

What is key here is that depending on the task specified by the user, we have a task-specific form of evaluation. This eventually allows us to better evaluate our individual generated examples. Samples of tasks for each category include

Classification: Natural Language Inference, Topic Classification and Sentiment Analysis
Short Form Generation : Question Answering and Sentence Completion
Long Form Generation : Text Summarization and Machine Translation
This helps to ultimately improve the performance of these large language models across different types of tasks.

Generate Single Response¶
Once we've selected our examples, the second step is relatively simple. We just need to append a few of our chosen examples that score best on our chosen metric to append to our solution.

Implementation¶
We've implemented a classification example below that tries to sample across different classes in a balanced manner before generating a response using a single inference call.

We bias this sampling towards samples that the model is more confident towards by using a confidence label.


```php
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
```
