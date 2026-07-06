---
title: 'Mixed Type Property'
docname: 'mixed_type_property'
id: '3d08'
tags:
  - 'basics'
  - 'mixed-types'
  - 'schema'
---
## Overview

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;

#[Description('Person profile extracted from a short biographical sentence')]
#[Instructions([
    'The name field must be the actual person name mentioned in the text.',
    'Do not use schema labels such as PersonProfile, UserProfile, or profile as the name.',
    'Use extraInfo for flexible details that do not have a dedicated top-level property.',
])]
class UserWithMixedTypeProperty
{
    #[Description('Actual person name from the source text')]
    #[Instructions('For the example text, extract Jason exactly.')]
    public string $name;

    #[Description('Flexible additional facts about the user, such as age, hobbies, interests, or travel preferences')]
    #[Instructions('Keep extraInfo as null only when no additional facts are present; otherwise use a compact JSON object or array.')]
    public mixed $extraInfo = null;
}

$text = <<<TEXT
    Jason is 25 years old. He plays football and loves to travel.
    TEXT;

$user = StructuredOutput::using('openai')
    ->withMessages($text)
    ->withResponseClass(UserWithMixedTypeProperty::class)
    ->get();

dump($user);

assert(trim($user->name) === "Jason");
assert($user->extraInfo === null || $user->extraInfo !== ''); // optional mixed field
?>
```
