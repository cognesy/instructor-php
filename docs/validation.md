### Basic validation

Instructor validates results of LLM response against validation rules specified in your data model.

!!! note
    
    For further details on available validation rules, check [Symfony Validation constraints](https://symfony.com/doc/current/validation.html#constraints).

```php
<?php

use Symfony\Component\Validator\Constraints as Assert;

class Person {
    public string $name;
    #[Assert\PositiveOrZero]
    public int $age;
}

$text = "His name is Jason, he is -28 years old.";
$person = (new Instructor(llm: $mockLLM))->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
);

// if the resulting object does not validate, Instructor throws an exception
```


### Max Retries

!!! example

    Run example via CLI: ```php ./examples/SelfCorrection/run.php```

In case maxRetries parameter is provided and LLM response does not meet validation criteria, Instructor will make subsequent inference attempts until results meet the requirements or maxRetries is reached.

Instructor uses validation errors to inform LLM on the problems identified in the response, so that LLM can try self-correcting in the next attempt.

```php
<?php

use Symfony\Component\Validator\Constraints as Assert;

class Person {
    #[Assert\Length(min: 3)]
    public string $name;
    #[Assert\PositiveOrZero]
    public int $age;
}

$text = "His name is JX, aka Jason, he is -28 years old.";
$person = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
    maxRetries: 3,
);

// if all LLM's attempts to self-correct the results fail, Instructor throws an exception
```

## Custom Validation

!!! example

    Run example via CLI: ```php ./examples/ValidationMixin/run.php```

You can easily add custom validation code to your response model by using ```ValidationTrait```
and defining validation logic in ```validate()``` method.

```php
<?php

use Cognesy\Instructor\Traits\ValidationMixin;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $age;

    public function validate() : array {
        if ($this->name === strtoupper($this->name)) {
            return [];
        }
        return [[
            'message' => "Name must be in uppercase.",
            'path' => 'name',
            'value' => $this->name
        ]];
    }
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => 'jason is 25 years old']],
    responseModel: UserDetails::class,
    maxRetries: 2
);

assert($user->name === "JASON");
```

Note that method ```validate()``` has to return:
 * an **empty array** if the object is valid,
 * or an array of validation violations.

This information will be used by LLM to make subsequent attempts to correct the response.

```php
$violations = [
    [
        'message' => "Error message with violation details.",
        'path' => 'path.to.property',
        'value' => '' // invalid value
    ],
    // ...other violations
];
``` 


## Custom Validation via Symfony #[Assert/Callback]

!!! example

    Run example via CLI: ```php ./examples/CustomValidator/run.php```


Instructor uses Symfony validation component to validate extracted data.

You can use ```#[Assert/Callback]``` annotation to build fully customized validation logic.

```php
<?php

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
            $context->buildViolation("Name must be in uppercase.")
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

assert($user->name === "JASON");
```

!!! note

    See [Symfony docs](https://symfony.com/doc/current/reference/constraints/Callback.html) for more details on how to use Callback constraint.

