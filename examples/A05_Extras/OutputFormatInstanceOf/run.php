---
title: 'Use different class for schema and output'
docname: 'output_format_instance_of'
id: 'e273'
---
## Overview

Sometimes you want to define the extraction schema using one class but receive
the result as a different class. This is useful when:

- You have a rich domain model for the LLM schema but want a simpler DTO for output
- You want to separate API contracts from internal representations
- You need different validation rules for input vs output

The `intoInstanceOf()` method allows you to specify a different target class for
deserialization while keeping the original class for schema generation.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

// Rich schema class with detailed structure (sent to LLM)
class UserProfile {
    public string $fullName;
    public int $age;
    public string $email;
    public string $phoneNumber;
    public string $address;
}

// Simplified DTO for output (subset of fields you need)
class UserDTO {
    public function __construct(
        public string $fullName = '',
        public string $email = '',
    ) {}
}

// Extract using UserProfile schema, but receive as UserDTO
$user = (new StructuredOutput)
    ->withResponseClass(UserProfile::class)      // Schema sent to LLM
    ->intoInstanceOf(UserDTO::class)             // Output class
    ->with(
        messages: "Extract: John Smith, 30 years old, john@example.com, phone: 555-1234, lives at 123 Main St",
    )
    ->get();

dump($user);

// Result is UserDTO instance (not UserProfile)
assert($user instanceof UserDTO);
assert(!($user instanceof UserProfile));

// UserDTO has only the fields it needs
assert($user->fullName === 'John Smith');
assert($user->email === 'john@example.com');

// UserDTO doesn't have the extra fields from UserProfile
assert(!property_exists($user, 'age'));
assert(!property_exists($user, 'phoneNumber'));
assert(!property_exists($user, 'address'));

echo "\nExtracted as UserDTO:\n";
echo "Name: {$user->fullName}\n";
echo "Email: {$user->email}\n";
?>
```

## Expected Output

```
object(UserDTO)#123 (2) {
  ["fullName"]=>
  string(10) "John Smith"
  ["email"]=>
  string(17) "john@example.com"
}

Extracted as UserDTO:
Name: John Smith
Email: john@example.com
```

## Note

The LLM receives the UserProfile schema (with 5 fields: name, age, email, phone, address),
but the result is deserialized into UserDTO (with only 2 fields: name, email).
Extra fields that don't exist in UserDTO are ignored during deserialization.
