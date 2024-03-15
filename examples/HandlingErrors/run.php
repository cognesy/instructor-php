# Handling errors

You can create a wrapper class to hold either the result of an operation or an error message. This allows you to remain within a function call even if an error occurs, facilitating better error handling without breaking the code flow.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class UserDetail
{
    public int $age;
    public string $name;
    public ?string $role = null;
}

class MaybeUser
{
    public ?UserDetail $result = null;
    public ?string $errorMessage = '';
    public bool $error = false;

    public function get(): ?UserDetail
    {
        return $this->error ? null : $this->result;
    }
}

$user = (new Instructor)->respond(
    [['role' => 'user', 'content' => 'We don\'t know anything about this guy.']],
    MaybeUser::class
);

dump($user);
?>
```

