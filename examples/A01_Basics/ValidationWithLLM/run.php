---
title: 'Validation with LLM'
docname: 'validation_with_llm'
---

## Overview

You can use LLM capability to semantically process the context to validate
the response following natural language instructions. This way you can
implement more complex validation logic that would be difficult (or impossible)
to achieve using traditional, code-based validation.

## Example
```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Str;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    #[Description('User details in format: key=value')]
    /** @var string[]  */
    public array $details;

    public function validate() : ValidationResult {
        return match($this->hasPII()) {
            true => ValidationResult::fieldError(
                field: 'details',
                value: implode('\n', $this->details),
                message: "Details contain PII, remove it from the response."
            ),
            false => ValidationResult::valid(),
        };
    }

    private function hasPII() : bool {
        $data = implode('\n', $this->details);
        return (new StructuredOutput)
            ->with(
                messages: "Context:\n$data\n",
                responseModel: Scalar::boolean('hasPII', 'Does the context contain any PII?'),
            )
            ->getBoolean();
    }
}

$text = <<<TEXT
    My name is Jason. I am is 25 years old. I am developer.
    My phone number is +1 123 34 45 and social security number is 123-45-6789
    TEXT;

$user = (new StructuredOutput)
    ->wiretap(fn(Event $e) => $e->print()) // let's check the internals of Instructor processing
    ->with(
        messages: $text,
        responseModel: UserDetails::class,
        maxRetries: 2
    )->get();

dump($user);

assert(!Str::contains(implode('\n', $user->details), '123-45-6789'));
?>
```
