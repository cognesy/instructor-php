# Consistent values of arbitrary properties

For multiple records containing arbitrary properties, instruct LLM to get more consistent key names when extracting properties.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Instructor;

class UserDetail
{
    public int $id;
    public string $key;
    public string $value;
}

class UserDetails
{
    /** @var UserDetail[] Extract information for multiple users.
     * Use consistent key names for properties across users.
     */
    public array $users;
}

$text = "Jason is 25 years old. He is a Python programmer. Amanda is UX designer. John is 40yo and he's CEO.";

$list = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetails::class
);

assert(!empty($list->users));
dump($list);
?>
```
