# Option Type Cheatsheet

## Quick Reference

```php
use Cognesy\Utils\Option\Option;

// Creating Options
$some = Option::some($value);           // Wrap any value
$none = Option::none();                 // Empty Option
$opt = Option::fromNullable($nullable); // null → None, anything else → Some
$opt = Option::fromResult($result);     // Convert Result to Option
```

## When to Use Option

✅ **USE Option when:**
- Representing optional/missing values instead of `null`
- Function might not return a value (search, lookup, parse)
- Chaining operations that might fail
- Avoiding null pointer exceptions
- Making optional parameters explicit

❌ **DON'T use Option for:**
- Values that are always present
- Simple boolean flags
- Error handling (use `Result` instead)

## Core Patterns

### Safe Value Access
```php
// Instead of null checks
$user = findUser($id);
if ($user !== null) {
    return $user->getName();
}
return 'Unknown';

// Use Option
findUser($id)
    ->map(fn($user) => $user->getName())
    ->getOrElse('Unknown');
```

### Chaining Operations
```php
// Avoid nested null checks
$result = null;
$user = findUser($id);
if ($user !== null) {
    $profile = $user->getProfile();
    if ($profile !== null) {
        $result = $profile->getEmail();
    }
}

// Chain with Option
findUser($id)
    ->flatMap(fn($user) => Option::fromNullable($user->getProfile()))
    ->map(fn($profile) => $profile->getEmail())
    ->getOrElse(null);
```

## API Reference

### Factories
```php
Option::some($value)              // Create Some with value
Option::none()                    // Create None
Option::fromNullable($value)      // null → None, value → Some
Option::fromResult($result)       // Convert Result to Option
```

### Queries
```php
$opt->isSome()                    // true if has value
$opt->isNone()                    // true if empty
$opt->exists(fn($x) => $x > 5)    // true if has value AND predicate matches
$opt->forAll(fn($x) => $x > 5)    // true if None OR predicate matches
```

### Transformations
```php
$opt->map(fn($x) => $x * 2)               // Transform value if present
$opt->flatMap(fn($x) => Option::some($x)) // Chain Optional operations
$opt->andThen(fn($x) => findRelated($x))  // Alias for flatMap
$opt->filter(fn($x) => $x > 0)            // Keep only if predicate true
$opt->zipWith($other, fn($a,$b) => $a+$b) // Combine two Options
```

### Side Effects
```php
$opt->ifSome(fn($x) => log("Found: $x"))  // Execute on Some, return self
$opt->ifNone(fn() => log("Not found"))    // Execute on None, return self
```

### Destructuring
```php
$opt->match(
    fn() => 'not found',          // None case
    fn($x) => "found: $x"         // Some case
);

$opt->getOrElse('default')        // Value or default
$opt->getOrElse(fn() => compute()) // Value or computed default
$opt->orElse(Option::some('alt')) // This Option or alternative
$opt->toNullable()                // Extract to nullable value
$opt->toResult($error)            // Convert to Result
$opt->toSuccessOr('default')      // Always Success Result
```

## Common Patterns

### Repository Pattern
```php
interface UserRepository {
    public function findById(int $id): Option; // Option<User>
    public function findByEmail(string $email): Option;
}

class UserService {
    public function getUserProfile(int $id): Option {
        return $this->repository
            ->findById($id)
            ->filter(fn($user) => $user->isActive())
            ->map(fn($user) => $user->getProfile());
    }
}
```

### Configuration Access
```php
class Config {
    public function get(string $key): Option {
        return Option::fromNullable($this->data[$key] ?? null);
    }

    public function getInt(string $key): Option {
        return $this->get($key)
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v);
    }
}

// Usage
$config->getInt('max_retries')
    ->filter(fn($x) => $x > 0)
    ->getOrElse(3);
```

### Parsing/Validation
```php
function parsePositiveInt(string $input): Option {
    return Option::fromNullable(filter_var($input, FILTER_VALIDATE_INT))
        ->filter(fn($x) => $x > 0);
}

// Chain parsers
parsePositiveInt($input)
    ->flatMap(fn($x) => $x < 100 ? Option::some($x) : Option::none())
    ->ifSome(fn($x) => processValue($x))
    ->ifNone(fn() => logError("Invalid input: $input"));
```

### Optional Parameters
```php
class QueryBuilder {
    private Option $limit;
    private Option $offset;

    public function limit(int $limit): self {
        $this->limit = Option::some($limit);
        return $this;
    }

    public function build(): string {
        $sql = "SELECT * FROM table";

        $this->limit->ifSome(fn($l) => $sql .= " LIMIT $l");
        $this->offset->ifSome(fn($o) => $sql .= " OFFSET $o");

        return $sql;
    }
}
```

### Error Recovery
```php
function getConfigValue(string $key): string {
    return getFromDatabase($key)
        ->orElse(fn() => getFromFile($key))
        ->orElse(fn() => getFromEnv($key))
        ->getOrElse('default');
}
```

## Best Practices

### ✅ DO
- Use meaningful variable names: `$maybeUser`, `$optionalEmail`
- Chain operations fluently
- Prefer `map` over `flatMap` when not returning Option
- Use `ifSome`/`ifNone` for side effects only
- Return Option from methods that might not find results

### ❌ DON'T
- Call `toNullable()` immediately after creation
- Use Option for error handling (use Result)
- Mix Option and nullable types in same codebase
- Create Option just to unwrap it

## Migration Strategy

### Phase 1: New Code
```php
// Start with new methods returning Option
public function findUser(int $id): Option {
    $user = $this->db->find($id);
    return Option::fromNullable($user);
}
```

### Phase 2: Gradual Replacement
```php
// Wrap existing nullable-returning methods
public function findUserLegacy(int $id): ?User {
    // existing implementation
}

public function findUser(int $id): Option {
    return Option::fromNullable($this->findUserLegacy($id));
}
```

### Phase 3: Full Adoption
```php
// Eventually replace nullable types entirely
public function processUser(int $id): Result {
    return $this->findUser($id)
        ->toResult(new UserNotFound($id))
        ->andThen(fn($user) => $this->validateUser($user))
        ->andThen(fn($user) => $this->saveUser($user));
}
```

## Performance Notes

- Option instances are immutable and lightweight
- Operations return new instances (functional style)
- Prefer method chaining over intermediate variables
- `flatMap` is more expensive than `map` (creates nested Options)
- Early termination: None shortcuts all subsequent operations

## IDE Support

```php
/** @return Option<User> */
public function findUser(int $id): Option;

/** @var Option<string> $email */
$email = $user->map(fn($u) => $u->getEmail());
```

Use PHPDoc annotations for better IDE support and static analysis.