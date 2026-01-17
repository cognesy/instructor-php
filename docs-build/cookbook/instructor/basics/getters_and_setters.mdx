---
title: 'Getters and setters'
docname: 'getters_and_setters'
---

## Overview

Instructor can extract data from the LLM response and use it
to instantiate an object via setter methods.

If given property is not public and has no matching constructor
params Instructor will use the setter method parameter nullability
and default value to determine if property is required.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;

class UserWithSetter
{
    #[Description('Name of the user or empty string if not provided')]
    private string $name;
    #[Description('Age of the user or 0 if not provided')]
    private ?int $age;
    #[Description('Location of the user or empty string if not provided')]
    private string $location;
    #[Description('Password of the user or empty string if not provided')]
    private string $password;

    // `name` is required (not nullable parameter), if data exists in the answer the setter will be called, but may have empty value
    public function setName(string $name): void {
        $this->name = $name ?: 'Jason';
    }

    public function getName(): string {
        return $this->name ?? '';
    }

    // `age` is optional (nullable parameter), setter will not be called if LLM does not infer the data
    public function setAge(int $age): void {
        $this->age = (int) $age;
    }

    public function getAge(): int {
        return $this->age ?? 0;
    }

    public function setLocation(?string $location): void {
        $this->location = $location;
    }

    public function getLocation(): string {
        return $this->location;
    }

    public function setPassword(string|null $password = ''): void {
        $this->password = $password ?: '123admin';
    }

    public function getPassword(): string {
        return $this->password;
    }
}

$text = <<<TEXT
    This user is living in San Francisco. His password is.
    TEXT;


$user = (new StructuredOutput)
    ->using('openai')
    //->withDebugPreset('on')
    ->withMessages($text)
    ->withResponseClass(UserWithSetter::class)
    ->withMaxRetries(2)
    //->withModel('claude-3-7-sonnet-20250219')
    ->get();

dump($user);

assert($user->getName() === "Jason"); // called - but set to default value as LLM inferred empty name
assert($user->getAge() === 0); // not called - property value not inferred by LLM
assert($user->getPassword() === '123admin'); // called - but set to default value as LLM inferred empty password
assert($user->getLocation() === 'San Francisco'); // called - LLM inferred location from the text
?>
```
