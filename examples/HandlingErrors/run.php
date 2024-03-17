# Handling errors

You can create a wrapper class to hold either the result of an operation or an error message.
This allows you to remain within a function call even if an error occurs, facilitating
better error handling without breaking the code flow.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class UserDetail
{
    public string $name;
    public int $age;
}

class MaybeUser
{
    public ?UserDetail $user = null;
    public bool $noUserData = false;
    /** If no user data, provide reason */
    public ?string $errorMessage = '';

    public function get(): ?UserDetail
    {
        return $this->noUserData ? null : $this->user;
    }
}

$user = (new Instructor)->respond(
    [['role' => 'user', 'content' => 'We don\'t know anything about this guy.']],
    MaybeUser::class
);

dump($user);

assert($user->noUserData);
assert(!empty($user->errorMessage));
assert($user->get() === null);
?>
```

