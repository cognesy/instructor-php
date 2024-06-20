---
title: 'Custom validation using Symfony Validator'
docname: 'custom_validation'
---

## Overview

Instructor uses Symfony validation component to validate properties of extracted data. Symfony
offers you #[Assert/Callback] annotation to build fully customized validation logic.


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');


use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Instructor;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserDetails
{
    public string $name;
    public int $age;

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context, mixed $payload) {
        if ($this->name !== strtoupper($this->name)) {
            $context->buildViolation("Name must be in all uppercase letters.")
                ->atPath('name')
                ->setInvalidValue($this->name)
                ->addViolation();
        }
    }
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
    responseModel: UserDetails::class,
    maxRetries: 2
);

dump($user);

assert($user->name === "JASON");
?>
```
