# Custom validation using Symfony Validator

Instructor uses Symfony validation component to validate extracted data. You can use #[Assert/Callback] annotation to build fully customized validation logic.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');


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

dump($user);
?>
```

