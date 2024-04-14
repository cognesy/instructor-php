# Prompt optimization

## Optimization of instructions

Quality of inference is highly dependent on the prompts. We need a better way to generate
instructions and examples (e.g. similar to DSPy).

## Stages of processing

- define: Define processing architecture from modules (layers). What are modules (layers)?
- process: Process the messages throught the flow defined in define().

## Stages of optimization

- evaluator() - evaluate results (e.g. vs golden data set)
- CanOptimize::optimize() - check results from evaluator and modify the instructions

```php
<?php


IdentifyProjectIssues::with($emailContent)
    ->inputs(Scalar::STRING)
    ->outputs(Sequence::of(Issue::class));

class Signature {}
class Transformer {}

class IdentifySpam extends Transformer {
}

class IdentifyProjectIssues extends Transformer {
    public function define() {
        $this->input('emails')->type(Collection::of(Scalar::string()));
        $this->output('issues')->type(Collection::of(Issue::class));
    }
    
    public function process() {
        foreach($this->inputs['emails'] as $email) {
            $this->outputs['issues'][] = 
        }
    }
}

?>
```
