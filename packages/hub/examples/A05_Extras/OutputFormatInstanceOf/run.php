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
