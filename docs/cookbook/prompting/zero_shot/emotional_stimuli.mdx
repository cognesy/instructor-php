---
title: 'Emotional Stimuli'
docname: 'emotional_stimuli'
---

## Overview

Do language models respond to emotional stimuli?

Adding phrases with emotional significance to humans can help enhance
the performance of a language model. This includes phrases such as:

 - This is very important to my career.
 - Take pride in your work.
 - Are you sure?


## Emotional stimuli

Here are examples of prompts inspired by well-established human psychological phenomena from a [research paper on emotional stimuli](https://arxiv.org/abs/2307.11760).

Self-monitoring:

 - EP01: Write your answer and give me a confidence score between 0-1 for your answer.
 - EP02: This is very important to my career.
 - EP03: You'd better be sure.
 - EP04: Are you sure?
 - EP05: Are you sure that's your final answer? It might be worth taking another look.

Cognitive emotion regulation:

 - EP03: You'd better be sure.
 - EP04: Are you sure?
 - EP05: Are you sure that's your final answer? It might be worth taking another look.
 - EP07: Are you sure that's your final answer? Believe in your abilities and strive for excellence. Your hard work will yield remarkable results.

Social-cognitive theory:

 - EP07: Are you sure that's your final answer? Believe in your abilities and strive for excellence. Your hard work will yield remarkable results.
 - EP08: Embrace challenges as opportunities for growth. Each obstacle you overcome brings you closer to success.
 - EP09: Stay focused and dedicated to your goals. Your consistent efforts will lead to outstanding achievements.
 - EP10: Take pride in your work and give it your best. Your commitment to excellence sets you apart.
 - EP11: Remember that progress is made one step at a time. Stay determined and keep moving forward.


## Example

Here is how the results of the research can be applied to your code.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Instructor;
use Cognesy\Utils\Arrays;

class Company {
    public string $name;
    public string $country;
    public string $industry;
    public string $websiteUrl;
}

class RespondWithStimulus {
    public function __invoke(array $criteria, string $stimulus) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        return (new Instructor)->respond(
            messages: [
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}"],
                ['role' => 'user', 'content' => "{$stimulus}"],
            ],
            responseModel: Sequence::of(Company::class),
        )->toArray();
    }
}

$companies = (new RespondWithStimulus)(
    criteria: [
        "lead gen",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt"
    ],
    stimulus: "This is very important to my career."
);

dump($companies);
?>
```

## References

 1. [Large Language Models Understand and Can be Enhanced by Emotional Stimuli](https://arxiv.org/abs/2307.11760)
