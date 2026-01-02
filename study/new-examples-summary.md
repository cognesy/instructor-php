# New OutputFormat Examples

Created 3 clear, simple examples demonstrating the new OutputFormat API capabilities.

## Examples Created

### 1. OutputFormatArray
**Location**: `examples/A05_Extras/OutputFormatArray/run.php`

**What it demonstrates**:
- Using `intoArray()` to receive raw associative arrays instead of objects
- Class defines schema (sent to LLM), but result is an array
- Useful for database storage, JSON APIs, or avoiding object overhead

**Key code**:
```php
$personArray = (new StructuredOutput)
    ->withResponseClass(Person::class)  // Schema definition
    ->intoArray()                        // Return as array
    ->get();

// Result: ['name' => 'Jason', 'age' => 25, 'occupation' => 'software engineer']
```

**Output**: Plain associative array with no object instantiation

---

### 2. OutputFormatInstanceOf
**Location**: `examples/A05_Extras/OutputFormatInstanceOf/run.php`

**What it demonstrates**:
- Using `intoInstanceOf()` to specify different class for output than schema
- Rich schema class (5 fields) → Simplified DTO (2 fields)
- Separating API contracts from internal representations

**Key code**:
```php
$user = (new StructuredOutput)
    ->withResponseClass(UserProfile::class)  // Schema (5 fields)
    ->intoInstanceOf(UserDTO::class)         // Output (2 fields)
    ->get();

// Result: UserDTO instance with only name and email
```

**Output**: UserDTO object (subset of UserProfile fields)

---

### 3. OutputFormatStreaming
**Location**: `examples/A05_Extras/OutputFormatStreaming/run.php`

**What it demonstrates**:
- Using `intoArray()` with streaming responses
- Partial updates during streaming are objects (for validation)
- Final result is an array (for convenience)
- Best of both worlds: validation during streaming, array for final result

**Key code**:
```php
$stream = (new StructuredOutput)
    ->withResponseClass(Article::class)
    ->intoArray()  // Final result will be array
    ->stream();

foreach ($stream->partials() as $partial) {
    // $partial is Article object during streaming
}

$finalArticle = $stream->finalValue();  // Returns array
```

**Output**: 
- During streaming: Article objects (30+ updates)
- Final result: Associative array with article data

---

## Example Structure Pattern

All examples follow the established pattern:

1. **Frontmatter**: `---` block with `title` and `docname`
2. **Overview**: 2-3 paragraphs explaining the use case
3. **Example**: Full working code with:
   - `require 'examples/boot.php'`
   - Class definitions
   - Execution code
   - `dump()` output
   - Assertions validating behavior
   - `echo` statements showing results
4. **Expected Output**: Shows what the user should see
5. **Optional Note/How It Works**: Additional explanation

---

## Design Principles Followed

### Simplicity
- Each example demonstrates ONE concept
- Minimal code required to show the feature
- Clear variable names and comments

### Clarity
- Class names describe their purpose (Person, UserProfile, UserDTO, Article)
- Inline comments explain key steps
- Assertions document expected behavior

### Real-World Applicability
- Examples show practical use cases (database storage, DTOs, streaming)
- Demonstrable output with concrete values
- Shows "why" you'd use each feature

---

## Testing Results

All examples tested and verified:

✅ **OutputFormatArray**: Returns `['name' => 'Jason', 'age' => 25, ...]`  
✅ **OutputFormatInstanceOf**: Returns `UserDTO` with 2 fields (not 5)  
✅ **OutputFormatStreaming**: 34 object updates → final array result

All assertions pass successfully.
