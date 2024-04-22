# Arbitrary properties

When you need to extract undefined attributes, use a list of key-value pairs.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

class Property
{
    public string $key;
    public string $value;
}

class UserDetail
{
    public int $age;
    public string $name;
    /** @var Property[] Extract any other properties that might be relevant */
    public array $properties;
}
?>
```

Now we can use this data model to extract arbitrary properties from a text message
in a form that is easier for future processing.

```php
<?php
$text = <<<TEXT
    Jason is 25 years old. He is a programmer. He has a car. He lives
    in a small house in Alamo. He likes to play guitar.
    TEXT;

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class,
    mode: Mode::Json,
);

dump($user);

assert($user->age === 25);
assert($user->name === "Jason");
assert(!empty($user->properties));
?>
```
