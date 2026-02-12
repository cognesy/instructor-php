---
title: 'Consider Higher-Level Context'
docname: 'higher_level_context'
id: '1496'
---
## Overview

Encourage the model to think through high-level context required to answer a query. Step-back prompting proceeds in two steps:
- Abstraction: Generate a more generic step-back question.
- Reasoning: Answer the original question using the abstracted response.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Extras\Sequence\Sequence;

class Stepback {
    public string $original_question;
    public string $abstract_question;
}

enum Degree: string { case Bachelors='Bachelors'; case Masters='Masters'; case PhD='PhD'; }

class Education {
    public Degree $degree;
    public string $school;
    public string $topic;
    public int $year;
}

class FinalResponse { public string $school; }

class StepBackPrompting {
    public function generateStepback(string $question) : Stepback {
        $examples = <<<TXT
Original Question: Which position did Knox Cunningham hold from May 1955 to Apr 1956?
Step-back Question: Which positions has Knox Cunningham held in his career?
Original Question: Who was the spouse of Anna Karina from 1968 to 1974?
Step-back Question: Who were the spouses of Anna Karina?
Original Question: Which team did Thierry Audel play for from 2007 to 2008?
Step-back Question: Which teams did Thierry Audel play for in his career?
TXT;
        $prompt = "You are an expert at world knowledge. Step back and paraphrase a question to a more generic step-back question, which is easier to answer.\n\n{$examples}\n\nNow, generate the step-back question for: {$question}";
        return (new StructuredOutput)->with(
            messages: [['role'=>'user','content'=>$prompt]],
            responseModel: Stepback::class,
        )->get();
    }

    public function askStepback(string $abstractQuestion) : array {
        return (new StructuredOutput)->with(
            messages: [['role'=>'user','content'=>$abstractQuestion]],
            responseModel: Sequence::of(Education::class),
        )->get()->toArray();
    }

    public function finalAnswer(Stepback $s, array $education) : FinalResponse {
        $eduSummary = array_map(fn(Education $e) => "{$e->degree->value}, {$e->school}, {$e->topic}, {$e->year}", $education);
        $msg = "Q: {$s->abstract_question}\nA: " . implode("; ", $eduSummary) . "\nQ: {$s->original_question}\nA:";
        return (new StructuredOutput)->with(
            messages: [['role'=>'user','content'=>$msg]],
            responseModel: FinalResponse::class,
        )->get();
    }
}

$sb = new StepBackPrompting();
$step = $sb->generateStepback('Estella Leopold went to which school between Aug 1954 and Nov 1954?');
$edu = $sb->askStepback($step->abstract_question);
$final = $sb->finalAnswer($step, $edu);
dump($step, $edu, $final);
?>
```

### References

1) Take a Step Back: Evoking Reasoning via Abstraction in Large Language Models (https://arxiv.org/abs/2310.06117)
2) The Prompt Report: A Systematic Survey of Prompting Techniques (https://arxiv.org/abs/2406.06608)
