# ValidationMixin

Sometimes property level validation is not enough - you may want to check values of multiple properties
and based on the combination of them decide to accept or reject the response. Or the assertions provided
by Symfony may not be enough for your use case.

In such case you can easily add custom validation code to your response model by:
- using `ValidationTrait`
- and defining validation logic in `validate()` method.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;
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

dump($user);

assert($user->name === "JASON");
?>
```