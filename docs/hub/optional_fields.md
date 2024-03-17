# Making some fields optional

Use PHP's nullable types by prefixing type name with question mark (?) to mark
component fields which are optional and set a default value to prevent undesired
defaults like empty strings.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

class UserRole
{
    public string $title;
}

class UserDetail
{
    public int $age;
    public string $name;
    public ?UserRole $role;
}

$user = (new Instructor)->respond(
    messages: [["role" => "user",  "content" => "Jason is 25 years old."]],
    responseModel: UserDetail::class,
);

dump($user);

assert(!isset($user->role));
?>
```
