# Attributes

Use PHP attributes to define the model's class / field metadata. It has been done previously, but reverted due to Symfony validation use.

```php
use Cognesy\Instructor\Attributes\Description;
use Cognesy\Instructor\Attributes\Examples;

class User {
    #[Description("User's name")]
    public string $name;
    #[Description("User's age")]
    public int $age;
    #[Description("User's role")]
    #[Examples("admin", "user", "guest")]
    public string $role;
}
```
