# Validation across multiple fields

Sometimes property level validation is not enough - you may want to check values of multiple
properties and based on the combination of them decide to accept or reject the response.
Or the assertions provided by Symfony may not be enough for your use case.

In such case you can easily add custom validation code to your response model by:
- using `ValidationMixin`
- and defining validation logic in `validate()` method.

In this example LLM should be able to correct typo in the message (graduation year we provided
is `1010` instead of `2010`) and respond with correct graduation year.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $birthYear;
    public int $graduationYear;

    public function validate() : ValidationResult {
        if ($this->graduationYear > $this->birthYear) {
            return ValidationResult::valid();
        }
        return ValidationResult::fieldError(
            field: 'graduationYear',
            value: $this->graduationYear,
            message: "Graduation year has to be after birth year.",
        );
    }
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => 'Jason was born in 1990 and graduated in 1010.']],
    responseModel: UserDetails::class,
    maxRetries: 2
);

dump($user);

assert($user->graduationYear === 2010);
?>
```