# Instructor PHP - Complete Capabilities Cheatsheet

Quick reference for using Instructor PHP to transform unstructured data into structured PHP objects.

## Table of Contents
- [Core API](#core-api)
- [Quick Start Patterns](#quick-start-patterns)
- [Data Extraction Use Cases](#data-extraction-use-cases)
- [Advanced Features](#advanced-features)
- [Helper Classes](#helper-classes)
- [Configuration](#configuration)
- [Event Handling](#event-handling)

---

## Core API

### `StructuredOutput` - Main Entry Point

```php
use Cognesy\Instructor\StructuredOutput;

$result = (new StructuredOutput)
    ->with(
        messages: $input,           // string, array, Message, or Messages
        responseModel: User::class, // class name, object, or JSON schema
        model: 'gpt-4o',           // optional: LLM model
        maxRetries: 3,             // optional: self-correction attempts
        options: [],               // optional: LLM-specific options
        mode: OutputMode::Json     // optional: output mode
    )
    ->get();
```

### Fluent API Methods

#### Request Configuration
```php
->withMessages(string|array|Message|Messages $messages)
->withResponseClass(string $class)
->withResponseObject(object $object)
->withModel(string $model)
->withMaxRetries(int $maxRetries)
->withStreaming(bool $stream = true)
```

#### Provider Selection
```php
->using(string $preset)  // 'openai', 'anthropic', 'gemini', 'cohere'
->withDsn(string $dsn)   // e.g., 'openai:gpt-4o'
```

#### Execution
```php
->get()        // Returns parsed result object
->response()   // Returns raw LLM response
->stream()     // Returns StructuredOutputStream
->getString()  // Returns result as string
->getInt()     // Returns result as integer
->getFloat()   // Returns result as float
```

---

## Quick Start Patterns

### 1. Basic Text → Object Extraction

```php
class User {
    public string $name;
    public int $age;
    public string $email;
}

$text = "Jason is 25 years old, email: jason@example.com";

$user = (new StructuredOutput)
    ->withMessages($text)
    ->withResponseClass(User::class)
    ->get();
```

### 2. With Validation

```php
use Symfony\Component\Validator\Constraints as Assert;

class Contact {
    public string $name;

    #[Assert\Email]
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/')]
    public ?string $phone;
}

$contact = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Contact::class,
        maxRetries: 3  // Retry with feedback on validation failures
    )
    ->get();
```

### 3. Extracting Multiple Records

```php
use Cognesy\Instructor\Extras\Sequence\Sequence;

class Person {
    public string $name;
    public int $age;
}

$text = "Team: Jason (25), Jane (18), John (30), Anna (28)";

$people = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Sequence::of(Person::class)
    )
    ->get();

// $people is array-like: count(), foreach, array access
foreach ($people as $person) {
    echo "{$person->name} is {$person->age}\n";
}
```

### 4. Extracting Scalar Values

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;

enum CompanySize : string {
    case Startup = "1-50";
    case SMB = "51-500";
    case Enterprise = "500+";
}

$text = "Fast-growing startup with 35 employees";

$size = (new StructuredOutput)
    ->with(
        messages: $text,
        prompt: 'What is the company size?',
        responseModel: Scalar::enum(CompanySize::class)
    )
    ->get();

// $size === CompanySize::Startup
```

### 5. Handling Optional/Missing Data

```php
use Cognesy\Instructor\Extras\Maybe\Maybe;

class CompanyInfo {
    public string $name;
    public string $industry;
}

$webpage = "Welcome to our site!"; // Incomplete info

$maybe = (new StructuredOutput)
    ->with(
        messages: $webpage,
        responseModel: Maybe::is(CompanyInfo::class)
    )
    ->get();

if ($maybe->hasValue()) {
    $company = $maybe->get();
    // Process company
} else {
    $error = $maybe->error();
    // Handle missing data gracefully
}
```

---

## Data Extraction Use Cases

### Use Case 1: Web Scraping → Structured Data

```php
use Cognesy\Auxiliary\Web\Webpage;

class Company {
    public string $name;
    public string $location;
    public string $description;
    public int $minProjectBudget;
    public array $clients;
}

$companies = [];
$companyGenerator = Webpage::withScraper('none')
    ->get('https://example.com/companies')
    ->cleanup()
    ->selectMany('.company-card', fn($item) => $item->asMarkdown(), limit: 10);

foreach ($companyGenerator as $companyHtml) {
    $company = (new StructuredOutput)
        ->with(
            messages: $companyHtml,
            responseModel: Company::class
        )
        ->get();
    $companies[] = $company;
}
```

### Use Case 2: Image → Data (Receipt, Business Card, Document)

```php
use Cognesy\Addons\Image\Image;

class Receipt {
    public Vendor $vendor;
    /** @var ReceiptItem[] */
    public array $items;
    public float $subtotal;
    public float $tax;
    public float $total;
}

class Vendor {
    public string $name;
    public ?string $address;
    public ?string $phone;
}

class ReceiptItem {
    public string $name;
    public int $quantity;
    public float $price;
}

$receipt = (new StructuredOutput)
    ->with(
        messages: Image::fromFile('receipt.jpg')->toMessage(),
        responseModel: Receipt::class,
        prompt: 'Extract structured data from this receipt.'
    )
    ->get();
```

### Use Case 3: Meeting Transcript → Tasks

```php
enum TaskStatus : string {
    case Pending = 'pending';
    case Completed = 'completed';
}

class Task {
    public string $title;
    public string $description;
    public DateTimeImmutable $dueDate;
    public string $owner;
    public TaskStatus $status;
}

class MeetingTasks {
    public DateTime $meetingDate;
    /** @var Task[] */
    public array $tasks;
}

$transcript = "Meeting on 2024-01-15: Dev to research APIs by Jan 20th...";

$tasks = (new StructuredOutput)
    ->with(
        messages: $transcript,
        responseModel: MeetingTasks::class
    )
    ->get();
```

### Use Case 4: Complex Nested Extraction

```php
class ProjectEvent {
    public string $title;
    public string $description;
    public ProjectEventType $type;     // enum: Risk, Issue, Action
    public ProjectEventStatus $status; // enum: Open, Closed
    /** @var Stakeholder[] */
    public array $stakeholders;
    public ?string $date;
}

class Stakeholder {
    public string $name;
    public StakeholderRole $role; // enum: Customer, Vendor, Partner
    public ?string $details;
}

$statusReport = "Project in RED status due to vendor delays...";

$events = (new StructuredOutput)
    ->with(
        messages: $statusReport,
        responseModel: Sequence::of(ProjectEvent::class),
        options: ['max_tokens' => 16000]
    )
    ->get();
```

### Use Case 5: Email/Notes → CRM Contact

```php
class ContactRecord {
    public string $name;
    public string $company;

    #[Assert\Email]
    public string $email;

    public ?string $phone;
    public ?string $role;
    public ?string $linkedin;
}

$email = "Met with Sarah Johnson from Acme Corp. She's VP of Sales,
          reach her at sarah.j@acmecorp.com or +1-555-0123.
          LinkedIn: linkedin.com/in/sarahjohnson";

$contact = (new StructuredOutput)
    ->with(
        messages: $email,
        responseModel: ContactRecord::class,
        maxRetries: 2
    )
    ->get();

// Save to CRM database
$crm->createContact([
    'name' => $contact->name,
    'company' => $contact->company,
    'email' => $contact->email,
    'phone' => $contact->phone,
    'role' => $contact->role,
    'linkedin' => $contact->linkedin,
]);
```

---

## Advanced Features

### Streaming with Partial Updates

Get real-time updates as LLM generates response:

```php
class UserDetail {
    public string $name;
    public int $age;
    public string $location;
    public array $roles;
    public array $hobbies;
}

$user = (new StructuredOutput)
    ->withMessages($text)
    ->withResponseClass(UserDetail::class)
    ->withStreaming()
    ->onPartialUpdate(function($partial) {
        // Update UI or process partial data
        echo "Progress: " . json_encode($partial) . "\n";
    })
    ->get();
```

### Sequence Streaming

Stream each completed item in a sequence:

```php
$people = (new StructuredOutput)
    ->onSequenceUpdate(fn($seq) => echo "New person: {$seq->last()->name}\n")
    ->with(
        messages: $text,
        responseModel: Sequence::of(Person::class),
        options: ['stream' => true]
    )
    ->get();
```

### Custom Prompts

```php
$result = (new StructuredOutput)
    ->with(
        messages: $text,
        prompt: 'Extract lead information focusing on decision-maker role',
        system: 'You are a CRM data extraction specialist',
        responseModel: Lead::class
    )
    ->get();
```

### Providing Examples

```php
$result = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Contact::class,
        examples: [
            [
                'input' => 'Call John Smith at Acme Corp (john@acme.com)',
                'output' => [
                    'name' => 'John Smith',
                    'company' => 'Acme Corp',
                    'email' => 'john@acme.com'
                ]
            ]
        ]
    )
    ->get();
```

### Multiple Output Modes

```php
use Cognesy\Polyglot\Inference\Enums\OutputMode;

// Tool calling (default for supported models)
->withOutputMode(OutputMode::Tools)

// JSON mode
->withOutputMode(OutputMode::Json)

// Markdown + JSON (fallback for older models)
->withOutputMode(OutputMode::MdJson)

// JSON Schema (for schema-aware models)
->withOutputMode(OutputMode::JsonSchema)
```

### Context Caching

For repeated requests with same context:

```php
$result = (new StructuredOutput)
    ->withCachedContext(
        messages: $longDocument,
        system: 'You are a data extraction expert'
    )
    ->with(
        prompt: 'Extract contact information',
        responseModel: Contact::class
    )
    ->get();
```

---

## Helper Classes

### `Sequence<T>` - Working with Lists

```php
use Cognesy\Instructor\Extras\Sequence\Sequence;

$sequence = Sequence::of(Person::class, 'people', 'List of people');

// Create via extraction
$people = (new StructuredOutput)
    ->with(messages: $text, responseModel: $sequence)
    ->get();

// Array-like access
$count = count($people);
$first = $people->first();
$last = $people->last();
$person = $people[0];

// Iteration
foreach ($people as $person) {
    echo $person->name;
}

// Manipulation
$people->push(new Person('Alice', 30));
$removed = $people->pop();
$all = $people->all();
$isEmpty = $people->isEmpty();
```

### `Scalar` - Extracting Simple Values

```php
use Cognesy\Instructor\Extras\Scalar\Scalar;

// String
$name = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Scalar::string('userName', 'User name')
    )
    ->get();

// Integer
$age = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Scalar::int('age', 'Age in years')
    )
    ->get();

// Boolean
$isActive = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Scalar::bool('active', 'Is user active?')
    )
    ->get();

// Enum
$status = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Scalar::enum(Status::class, 'status')
    )
    ->get();
```

### `Maybe<T>` - Optional Values with Error Info

```php
use Cognesy\Instructor\Extras\Maybe\Maybe;

$maybeUser = (new StructuredOutput)
    ->with(
        messages: $incompleteeData,
        responseModel: Maybe::is(User::class, 'user', 'User if found')
    )
    ->get();

if ($maybeUser->hasValue()) {
    $user = $maybeUser->get();
    // Process user
} else {
    $error = $maybeUser->error();
    // Handle gracefully: log error, show message, etc.
    echo "Could not extract user: $error";
}
```

### `Structure` - Dynamic Object Creation

```php
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Structure\Field;

$structure = Structure::define('person', [
    Field::string('name', 'Full name'),
    Field::int('age', 'Age in years'),
    Field::enum('status', Status::class, 'Current status'),
    Field::collection('hobbies', 'string', 'List of hobbies'),
    Field::object('address', Address::class, 'Home address'),
]);

$result = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: $structure
    )
    ->get();

// Access fields
$name = $result->get('name');
$age = $result->age; // Magic property access
```

---

## Configuration

### Provider Selection

```php
// Using presets
->using('openai')     // OpenAI (default)
->using('anthropic')  // Anthropic Claude
->using('gemini')     // Google Gemini
->using('cohere')     // Cohere

// Using DSN
->withDsn('openai:gpt-4o')
->withDsn('anthropic:claude-3-5-sonnet-20241022')
->withDsn('gemini:gemini-2.0-flash-exp')
```

### Model Options

```php
->withOptions([
    'max_tokens' => 4096,
    'temperature' => 0.7,
    'top_p' => 0.9,
])

// Or set individually
->withOption('max_tokens', 4096)
->withOption('temperature', 0.7)
```

### Custom Configuration

```php
use Cognesy\Instructor\Configuration\StructuredOutputConfig;
use Cognesy\Polyglot\Configuration\LLMConfig;

$config = new StructuredOutputConfig(
    llmConfig: new LLMConfig(
        provider: 'openai',
        model: 'gpt-4o',
        apiKey: $_ENV['OPENAI_API_KEY'],
    ),
    maxRetries: 3,
    mode: OutputMode::Json
);

$result = (new StructuredOutput)
    ->withConfig($config)
    ->with(messages: $text, responseModel: User::class)
    ->get();
```

---

## Event Handling

### Listening to Specific Events

```php
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Events\Response\ResponseValidated;

$result = (new StructuredOutput)
    ->onEvent(ResponseValidationAttempt::class,
        fn($e) => echo "Validating...\n")
    ->onEvent(ResponseValidationFailed::class,
        fn($e) => echo "Failed: {$e->getErrors()}\n")
    ->onEvent(ResponseValidated::class,
        fn($e) => echo "Success!\n")
    ->with(
        messages: $text,
        responseModel: Contact::class,
        maxRetries: 3
    )
    ->get();
```

### Wiretap (Listen to All Events)

```php
$result = (new StructuredOutput)
    ->wiretap(function($event) {
        echo get_class($event) . "\n";
        dump($event);
    })
    ->with(messages: $text, responseModel: User::class)
    ->get();
```

---

## Common Patterns

### Pattern 1: CRM Lead Capture from URL

```php
use Cognesy\Auxiliary\Web\Webpage;
use Cognesy\Instructor\Extras\Maybe\Maybe;

class Lead {
    public string $name;
    public string $company;
    #[Assert\Email]
    public string $email;
    public ?string $phone;
    public ?string $jobTitle;
}

function captureLead(string $url): ?Lead {
    // Fetch webpage
    $content = Webpage::withScraper('scrapingbee')
        ->get($url)
        ->cleanup()
        ->asMarkdown();

    // Extract with validation
    $maybeLead = (new StructuredOutput)
        ->with(
            messages: $content,
            responseModel: Maybe::is(Lead::class),
            maxRetries: 3
        )
        ->get();

    return $maybeLead->hasValue() ? $maybeLead->get() : null;
}
```

### Pattern 2: Bulk Contact Import from Text

```php
function importContacts(string $text): array {
    $contacts = (new StructuredOutput)
        ->with(
            messages: $text,
            responseModel: Sequence::of(Contact::class),
            maxRetries: 2
        )
        ->get();

    $imported = [];
    foreach ($contacts as $contact) {
        if (!contactExists($contact->email)) {
            $id = saveContact($contact);
            $imported[] = $id;
        }
    }

    return $imported;
}
```

### Pattern 3: Document Processing with Progress

```php
function processDocument(string $filePath): Deal {
    $text = extractTextFromPdf($filePath);

    $deal = (new StructuredOutput)
        ->withMessages($text)
        ->withResponseClass(Deal::class)
        ->withStreaming()
        ->onPartialUpdate(function($partial) {
            // Broadcast progress to UI
            broadcast('deal-extraction-progress', [
                'company' => $partial->company ?? 'Extracting...',
                'value' => $partial->dealValue ?? 0,
                'stage' => $partial->stage ?? 'Unknown',
            ]);
        })
        ->get();

    return $deal;
}
```

### Pattern 4: Self-Correcting Data Quality

```php
class ValidatedContact {
    public string $name;

    #[Assert\Email]
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/')]
    #[Assert\NotBlank]
    public string $phone;
}

function extractContact(string $messyInput): ?ValidatedContact {
    try {
        return (new StructuredOutput)
            ->with(
                messages: $messyInput,
                responseModel: ValidatedContact::class,
                maxRetries: 5  // Allow multiple correction attempts
            )
            ->get();
    } catch (ValidationException $e) {
        // Even after 5 retries, data quality requirements not met
        logValidationFailure($messyInput, $e->getErrors());
        return null;
    }
}
```

### Pattern 5: Classification & Enrichment

```php
enum LeadSource : string {
    case Website = 'website';
    case Referral = 'referral';
    case Event = 'event';
    case Cold = 'cold';
}

enum LeadQuality : string {
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';
}

class EnrichedLead {
    public string $name;
    public string $company;
    public string $email;
    public LeadSource $source;
    public LeadQuality $quality;
    public int $score; // 0-100
    public array $interests;
    public ?string $nextAction;
}

$lead = (new StructuredOutput)
    ->with(
        messages: $leadText,
        prompt: 'Extract and classify this lead. Score based on: company size,
                 decision-maker role, explicit interest, urgency indicators.',
        responseModel: EnrichedLead::class
    )
    ->get();
```

---

## Validation Constraints

Common Symfony validation constraints for CRM data:

```php
use Symfony\Component\Validator\Constraints as Assert;

class CRMRecord {
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $name;

    #[Assert\Email]
    #[Assert\NotBlank]
    public string $email;

    #[Assert\Regex(pattern: '/^\+?[1-9]\d{1,14}$/')]
    public ?string $phone;

    #[Assert\Url]
    public ?string $website;

    #[Assert\Range(min: 0, max: 100)]
    public ?int $score;

    #[Assert\Choice(choices: ['lead', 'prospect', 'customer'])]
    public string $stage;

    #[Assert\Positive]
    public ?float $dealValue;

    #[Assert\Date]
    public ?string $followUpDate;
}
```

---

## Best Practices

### 1. Always Use Validation for Critical Fields
```php
// Good: Email validation prevents bad data
#[Assert\Email]
public string $email;

// Bad: No validation, garbage may enter DB
public string $email;
```

### 2. Use `maxRetries` for Self-Correction
```php
// Good: LLM gets 3 chances to fix validation errors
->with(messages: $text, responseModel: Contact::class, maxRetries: 3)

// Acceptable: No retries for simple extractions
->with(messages: $text, responseModel: Contact::class)
```

### 3. Handle Missing Data with `Maybe`
```php
// Good: Graceful handling of incomplete data
$maybe = (new StructuredOutput)
    ->with(messages: $scrapedHtml, responseModel: Maybe::is(Company::class))
    ->get();

if ($maybe->hasValue()) {
    // Process
} else {
    // Log and continue
}

// Bad: Exception on missing data breaks flow
$company = (new StructuredOutput)
    ->with(messages: $scrapedHtml, responseModel: Company::class)
    ->get(); // Throws if data incomplete
```

### 4. Use Streaming for Long Documents
```php
// Good: Shows progress for multi-page PDFs
->withStreaming()
->onPartialUpdate(fn($p) => updateProgress($p))

// Bad: User waits with no feedback
->get()
```

### 5. Provide Examples for Complex Structures
```php
// Good: Examples guide LLM on complex formats
->with(
    messages: $text,
    responseModel: Deal::class,
    examples: [
        ['input' => '...', 'output' => [...]]
    ]
)
```

---

## Troubleshooting

### Validation Keeps Failing

```php
// Enable event logging to see what's failing
->onEvent(ResponseValidationFailed::class, function($event) {
    echo "Validation errors:\n";
    dump($event->getErrors());
})
->with(messages: $text, responseModel: Contact::class, maxRetries: 3)
```

### Extraction Is Inaccurate

```php
// Add custom prompt with specific instructions
->with(
    messages: $text,
    prompt: 'Extract email addresses. Common formats: john@example.com,
             john.doe@company.co.uk. Watch for "at" spelled out.',
    responseModel: Contact::class
)

// Or provide examples
->with(
    messages: $text,
    responseModel: Contact::class,
    examples: [
        [
            'input' => 'Contact john at example dot com',
            'output' => ['email' => 'john@example.com']
        ]
    ]
)
```

### Streaming Not Working

```php
// Ensure streaming is enabled + use partial update handler
->withStreaming()
->onPartialUpdate(fn($partial) => dump($partial))
->with(messages: $text, responseModel: User::class)
->get(); // NOTE: Use get(), not stream()

// For manual streaming control
$stream = (new StructuredOutput)
    ->withStreaming()
    ->with(messages: $text, responseModel: User::class)
    ->stream();

foreach ($stream->partials() as $partial) {
    // Process
}
```

---

## API Reference Summary

### Most Commonly Used Methods

```php
// Request building
->with(messages, responseModel, model?, maxRetries?, options?, mode?)
->withMessages($text)
->withResponseClass(User::class)
->withModel('gpt-4o')
->withMaxRetries(3)
->withStreaming()

// Provider selection
->using('openai')
->withDsn('anthropic:claude-3-5-sonnet-20241022')

// Execution
->get()        // Get result object
->response()   // Get raw LLM response
->stream()     // Get streaming handler

// Event handling
->onEvent($eventClass, $callback)
->onPartialUpdate($callback)
->onSequenceUpdate($callback)
->wiretap($callback)
```

### Helper Classes

```php
// Sequences (lists of objects)
Sequence::of(Person::class)

// Optional values with error handling
Maybe::is(User::class)

// Scalar values (string, int, bool, enum)
Scalar::string('name')
Scalar::int('age')
Scalar::enum(Status::class)

// Dynamic structures
Structure::define('person', [
    Field::string('name'),
    Field::int('age'),
])

// Examples
Example::fromText($input, $output)
```

---

## Quick Reference Card

| Task | Pattern |
|------|---------|
| Extract object from text | `->with(messages: $text, responseModel: User::class)->get()` |
| Extract multiple records | `->with(messages: $text, responseModel: Sequence::of(User::class))->get()` |
| Extract single value | `->with(messages: $text, responseModel: Scalar::string('name'))->get()` |
| Handle missing data | `->with(messages: $text, responseModel: Maybe::is(User::class))->get()` |
| Validate and retry | `->with(messages: $text, responseModel: User::class, maxRetries: 3)->get()` |
| Stream with progress | `->withStreaming()->onPartialUpdate($cb)->with(...)->get()` |
| Extract from image | `->with(messages: Image::fromFile($path)->toMessage(), ...)->get()` |
| Extract from web | `Webpage::get($url)->asMarkdown()` then extract |
| Change provider | `->using('anthropic')->with(...)->get()` |
| Listen to events | `->onEvent(EventClass::class, $callback)->with(...)->get()` |

---

## Resources

- **GitHub**: https://github.com/cognesy/instructor-php
- **Documentation**: https://github.com/cognesy/instructor-php/tree/main/docs
- **Examples**: https://github.com/cognesy/instructor-php/tree/main/examples

---

Last updated: 2026-01-04
