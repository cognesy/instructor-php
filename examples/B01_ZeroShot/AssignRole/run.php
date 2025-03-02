---
title: 'Assign a Role'
docname: 'assign_role'
---

## Overview

How can we increase a model's performance on open-ended tasks?

Role prompting, or persona prompting, assigns a role to the model. Roles can be:
 - specific to the query: You are a talented writer. Write me a poem.
 - general/social: You are a helpful AI assistant. Write me a poem.

## More Role Prompting

To read about a systematic approach to choosing roles, check out [RoleLLM](https://arxiv.org/abs/2310.00746).

For more examples of social roles, check out this [evaluation of social roles in system prompts](https://arxiv.org/abs/2311.10054).

To read about using more than one role, check out [Multi-Persona Self-Collaboration](https://arxiv.org/abs/2307.05300).


## Example
```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Instructor;
use Cognesy\Utils\Arrays;

class Company {
    public string $name;
    public string $country;
    public string $industry;
    public string $websiteUrl;
}

class GenerateLeads {
    public function __invoke(array $criteria, array $roles) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        $rolesStr = Arrays::toBullets($roles);
        return (new Instructor)->respond(
            messages: [
                ['role' => 'user', 'content' => "Your roles:\n{$rolesStr}\n\n"],
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}\n\n"],
            ],
            responseModel: Sequence::of(Company::class),
        )->toArray();
    }
}

$companies = (new GenerateLeads)(
    criteria: [
        "insurtech",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt",
    ],
    roles: [
        "insurtech expert",
        "active participant in VC ecosystem",
    ]
);

dump($companies);
?>
```

## References

1. [RoleLLM: Benchmarking, Eliciting, and Enhancing Role-Playing Abilities of Large Language Models](https://arxiv.org/abs/2310.00746)
2. [Is "A Helpful Assistant" the Best Role for Large Language Models? A Systematic Evaluation of Social Roles in System Prompts](https://arxiv.org/abs/2311.10054)
3. [Unleashing the Emergent Cognitive Synergy in Large Language Models: A Task-Solving Agent through Multi-Persona Self-Collaboration](https://arxiv.org/abs/2307.05300)

