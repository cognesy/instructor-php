---
title: 'Define Style'
docname: 'define_style'
---

## Overview

How can we constrain model outputs through prompting alone?

To constrain a model's response to fit the boundaries of our task, we can specify a style.

Stylistic constraints can include:
 - writing style: write a flowery description
 - tone: write a dramatic description
 - mood: write a happy description
 - genre: write a journalistic description


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Utils\Arrays;

class Company {
    public string $name;
    public string $country;
    public string $industry;
    public string $websiteUrl;
    public string $description;
}

class GenerateCompanyProfiles {
    public function __invoke(array $criteria, array $styles) : array {
        $criteriaStr = Arrays::toBullets($criteria);
        $stylesStr = Arrays::toBullets($styles);
        return (new StructuredOutput)->create(
            messages: [
                ['role' => 'user', 'content' => "List companies meeting criteria:\n{$criteriaStr}\n\n"],
                ['role' => 'user', 'content' => "Use following styles for descriptions:\n{$stylesStr}\n\n"],
            ],
            responseModel: Sequence::of(Company::class),
        )->get()->toArray();
    }
}

$companies = (new GenerateCompanyProfiles)(
    criteria: [
        "insurtech",
        "located in US, Canada or Europe",
        "mentioned on ProductHunt"
    ],
    styles: [
        "brief", // "witty",
        "journalistic", // "buzzword-filled",
    ]
);

dump($companies);
?>
```

## References

 1. [Bounding the Capabilities of Large Language Models in Open Text Generation with Prompt Constraints](https://arxiv.org/abs/2302.09185)
