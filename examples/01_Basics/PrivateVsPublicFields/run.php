# Private vs public object field

Instructor only sets public fields of the object with the data provided by LLM.
Private and protected fields are left unchanged. If you want to access them
directly after extraction, consider providing default values for them.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class User
{
    public string $name;
    public int $age;
    public string $password = '';

    public function getAge(): int {
        return $this->age;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

class UserWithPrivateField
{
    public string $name;
    private int $age = 0;
    private string $password = '';

    public function getAge(): int {
        return $this->age;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

$text = <<<TEXT
    Jason is 25 years old. His password is '123admin'.
    TEXT;


// CASE 1: Class with public fields

$user = (new Instructor)->respond(
    messages: $text,
    responseModel: User::class
);

echo "User with public fields\n";
dump($user);

assert($user->name === "Jason");
assert($user->getAge() === 25);
assert($user->getPassword() === '123admin');


// CASE 2: Class with some private fields

$userPriv = (new Instructor)->respond(
    messages: $text,
    responseModel: UserWithPrivateField::class,
);

echo "User with private 'password' and 'age' fields\n";
dump($userPriv);

assert($userPriv->name === "Jason");
assert($userPriv->getAge() === 0);
assert($userPriv->getPassword() === '');
?>
```
