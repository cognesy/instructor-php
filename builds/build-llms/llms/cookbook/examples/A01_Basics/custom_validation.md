---
title: 'Custom validation using Symfony Validator'
docname: 'custom_validation'
id: '13a9'
tags:
  - 'basics'
  - 'validation'
  - 'symfony-validator'
---
## Overview

Instructor uses Symfony validation component to validate properties of extracted data. Symfony
offers you #[Assert/Callback] annotation to build fully customized validation logic.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserDetails
{
    public string $name;
    public int $age;

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context, mixed $payload) {
        if ($this->name !== strtoupper($this->name)) {
            $context->buildViolation("Name must be all uppercase.")
                ->atPath('name')
                ->setInvalidValue($this->name)
                ->addViolation();
        }
    }
}

$runtime = StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'))
    ->withMaxRetries(2)
    ->wiretap(fn($e) => $e->print());

$user = (new StructuredOutput($runtime))
    ->with(
        messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
        responseModel: UserDetails::class,
    )
    ->get();

dump($user);

assert($user->name === "JASON");
?>
```
