# Automatic correction based on validation results

Instructor uses validation errors to inform LLM on the problems identified
in the response, so that LLM can try self-correcting in the next attempt.

In case maxRetries parameter is provided and LLM response does not meet
validation criteria, Instructor will make subsequent inference attempts
until results meet the requirements or maxRetries is reached.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;use Symfony\Component\Validator\Constraints as Assert;

class UserDetails
{
    public string $name;
    #[Assert\Email]
    public string $email;
}

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => "you can reply to me via jason@gmailcom -- Jason"]],
    responseModel: UserDetails::class,
    maxRetries: 2
);

dump($user);

assert($user->email === "jason@gmail.com");
?>
```
