# Handling errors with `Maybe` helper class

You can create a wrapper class to hold either the result of an operation or an error message.
This allows you to remain within a function call even if an error occurs, facilitating
better error handling without breaking the code flow.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\Instructor;

class User
{
    public string $name;
    public int $age;
}


$text = 'We don\'t know anything about this guy.';
echo "\nINPUT:\n$text\n";

$maybeUser = (new Instructor)->respond(
    [['role' => 'user', 'content' => $text]],
    Maybe::is(User::class)
);

echo "\nOUTPUT:\n";
dump($maybeUser->get());

assert($maybeUser->hasValue() === false);
assert(!empty($maybeUser->error()));
assert($maybeUser->get() === null);

$text = "Jason is our new developer, he is 25 years old.";
echo "\nINPUT:\n$text\n";

$maybeUser = (new Instructor)->respond(
    [['role' => 'user', 'content' => $text]],
    Maybe::is(User::class)
);

echo "\nOUTPUT:\n";
dump($maybeUser->get());

assert($maybeUser->hasValue() === true);
assert(empty($maybeUser->error()));
assert($maybeUser->get() != null);
assert($maybeUser->get() instanceof User);
?>
```
