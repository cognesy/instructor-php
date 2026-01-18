<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

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
```php
<?php
$text = <<<TEXT
    Jason is 25 years old. He is a programmer. He has a car. He lives
    in a small house in Alamo. He likes to play guitar.
    TEXT;

$user = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class,
    mode: OutputMode::Json,
)->get();

dump($user);

assert($user->age === 25);
assert($user->name === "Jason");
assert(!empty($user->properties));
?>
