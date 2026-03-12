---
title: Messages
description: Message store, content parts, tool calls, and multi-section conversation management
package: messages
---

# Messages Package - Deep Reference

## Core Architecture

### MessageStore System

The MessageStore system provides multi-section message management for complex conversational scenarios:

```php
class MessageStore {
    public Sections $sections;
    public Metadata $parameters;
}

// Usage patterns
MessageStore::fromSections($section1, $section2);
MessageStore::fromMessages($messages, 'section_name');

// Fluent API
$store->section('system')->appendMessages($messages);
$store->section('prompt')->setMessages($messages);
$store->section('examples')->remove();
$store->parameters()->setParameter('model', 'gpt-4');
```

### Message System Contracts
```php
interface CanProvideMessage {
    public function toMessage(): Message;
}

interface CanProvideMessages {
    public function toMessages(): Messages;
}

interface CanRenderMessages {
    public function renderMessages(Messages $messages, array $parameters = []): Messages;
}
```

### Message Role System
```php
enum MessageRole: string {
    case System = 'system';
    case Developer = 'developer';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}

// Role utilities
MessageRole::fromString('user');           // Parse from string
MessageRole::fromAny($stringOrEnum);       // Flexible parsing
$role->is(MessageRole::User);              // Compare roles
$role->isNot(MessageRole::System);         // Negated comparison
$role->oneOf(MessageRole::User, MessageRole::Assistant);  // Multiple check
$role->isSystem();                         // System or Developer check
MessageRole::normalizeArray($mixedRoles);  // Normalize array of roles
```

### Content Type Enumeration
```php
enum ContentType: string {
    case Text = 'text';
    case Image = 'image_url';
    case File = 'file';
    case Audio = 'input_audio';
}
```

### Message Type Enumeration
```php
enum MessageType: string {
    case Text = 'text';                          // Regular text message (any role)
    case AssistantToolCalls = 'assistant_tool_calls'; // Assistant message with tool calls
    case ToolResult = 'tool_result';             // Tool role message with result
}

// Derived from message role + tool state via Message::type()
$message->type();                                // MessageType enum
```

## Identity Types

### MessageId
```php
final readonly class MessageId {
    public function __construct(public string $value);

    MessageId::generate();                        // Generate UUID-based ID
    $id->toString();                              // string
    $id->__toString();                            // string (magic method)
    $id->equals(MessageId $other);                // bool
}
```

### MessageSessionId
```php
final readonly class MessageSessionId {
    public function __construct(public string $value);

    MessageSessionId::generate();                 // Generate UUID-based ID
    $id->toString();                              // string
    $id->__toString();                            // string (magic method)
    $id->equals(MessageSessionId $other);         // bool
}
```

## Tool Types

### ToolCallId
```php
// Opaque external identifier for tool calls
final readonly class ToolCallId extends OpaqueExternalId {}

new ToolCallId('call_abc123');
$id->toString();                              // 'call_abc123'
$id->toNullableString();                      // 'call_abc123' or null
```

### ToolCall
```php
final readonly class ToolCall {
    public function __construct(
        string $name,
        array $arguments = [],
        ToolCallId|string|null $id = null,     // Accepts string, ToolCallId, or null
    );

    // Construction
    ToolCall::fromArray($data);                // From array (OpenAI format)
    ToolCall::none();                          // Sentinel "(no-tool)"

    // Accessors
    $tc->id();                                 // ?ToolCallId
    $tc->idString();                           // string ('' if null)
    $tc->name();                               // string
    $tc->arguments();                          // array
    $tc->args();                               // array (alias for arguments)
    $tc->argumentsAsJson();                    // string (JSON-encoded)
    $tc->argsAsJson();                         // string (alias for argumentsAsJson)
    $tc->hasArgs();                            // bool
    $tc->hasValue();                           // bool
    $tc->value();                              // mixed (first argument value)
    $tc->toArray();                            // Serialized array
    $tc->toString();                           // String representation

    // Mutation (immutable)
    $tc->with($name, $args, $id);             // Replace all fields
    $tc->withId($id);                          // Replace ID
    $tc->withName($name);                      // Replace name
    $tc->withArguments($args);                 // Replace arguments
    $tc->withArgs($args);                      // Alias for withArguments
}
```

### ToolCalls
```php
final readonly class ToolCalls {
    public function __construct(ToolCall ...$toolCalls);

    // Construction
    ToolCalls::empty();
    ToolCalls::fromArray($array);              // From array of ToolCall/array/string
    ToolCalls::fromMapper($items, $mapper);    // Custom mapping

    // Access
    $tcs->all();                               // ToolCall[]
    $tcs->first();                             // ?ToolCall
    $tcs->last();                              // ?ToolCall
    $tcs->count();                             // int
    $tcs->isEmpty();                           // bool
    $tcs->hasAny();                            // bool (not empty)
    $tcs->hasNone();                           // bool (empty)
    $tcs->hasSingle();                         // bool (exactly one)
    $tcs->hasMany();                           // bool (more than one)

    // Iteration
    $tcs->each();                              // iterable<ToolCall>
    $tcs->map(fn(ToolCall $tc) => ...);        // array
    $tcs->filter(fn(ToolCall $tc) => ...);     // ToolCalls
    $tcs->reduce($callback, $initial);         // mixed

    // Mutation (immutable)
    $tcs->withAddedToolCall($name, $args);     // Add a tool call
    $tcs->withLastToolCallUpdated($name, $json); // Update last call's name/args

    // Serialization
    $tcs->toArray();                           // array
    $tcs->toString();                          // string
}
```

### ToolResult
```php
final readonly class ToolResult {
    public function __construct(
        string $content,
        ToolCallId|string|null $callId = null, // Accepts string, ToolCallId, or null
        ?string $toolName = null,
        bool $isError = false,
    );

    // Factory methods
    ToolResult::success($content, $callId, $toolName);
    ToolResult::error($content, $callId, $toolName);
    ToolResult::fromArray($data);

    // Accessors
    $tr->content();                            // string
    $tr->callId();                             // ?ToolCallId
    $tr->callIdString();                       // string ('' if null)
    $tr->toolName();                           // ?string
    $tr->isError();                            // bool
    $tr->toArray();                            // Serialized array
}
```

## Message Class Structure

### Core Message Class
```php
final readonly class Message {
    protected string $role;
    protected string $name;
    protected Content $content;
    protected ToolCalls $toolCalls;
    protected ?ToolResult $toolResult;
    protected Metadata $metadata;
    protected ?MessageId $id;
    protected ?MessageId $parentId;

    public const DEFAULT_ROLE = 'user';
}
```

### Message Construction Patterns
```php
// Basic construction
new Message($role, $content, $name = '', $metadata = [], $toolCalls = null, $toolResult = null);
new Message(MessageRole::User, 'Hello world');
new Message('', 'Content');  // Defaults to 'user' role

// Factory methods
Message::empty();                          // Empty message
Message::make($role, $content, $name);     // Explicit construction
Message::asUser($message);                 // Force user role
Message::asAssistant($message);            // Force assistant role
Message::asSystem($message);               // Force system role
Message::asDeveloper($message);            // Force developer role
Message::asTool($message);                 // Force tool role

// Flexible construction
Message::fromAny($input, $role = null);    // Universal constructor
Message::fromString($content, $role);      // From string
Message::fromArray($messageArray);         // From OpenAI format
Message::fromContent($content, $role);     // From Content object
Message::fromContentPart($part, $role);    // From ContentPart
Message::fromInput($input, $role);         // From various inputs
Message::fromImage($image, $role);         // From Image object
```

### Message Input Resolution
```php
// fromAny() resolution logic
match(true) {
    is_string($message) => new Message(role: $role, content: $message),
    is_array($message) => Message::fromArray($message),
    $message instanceof Message => $message,
    $message instanceof Content => Message::fromContent($message),
    $message instanceof ContentPart => Message::fromContentPart($message),
    $message instanceof ContentParts => Message::fromContent(Content::fromParts($message)),
}

// Array validation and parsing
Message::isMessage($array);                // Check OpenAI format
Message::fromArray($array) supports:
- Standard format: ['role' => 'user', 'content' => 'text']
- Array of strings: ['Hello', 'World'] -> Content with multiple parts
- Metadata: ['role' => 'user', 'content' => 'text', '_metadata' => []]
- Tool calls: ['role' => 'assistant', 'tool_calls' => [...]]
- Tool result: ['role' => 'tool', 'content' => '...', 'tool_call_id' => '...']
- IDs: ['id' => '...', 'parentId' => '...', 'createdAt' => '...']
```

## Content System Architecture

### Content Class Structure
```php
final readonly class Content {
    protected ContentParts $parts;

    // Content state classification
    public function isComposite(): bool;    // Multi-part or complex content
    public function isEmpty(): bool;        // All parts empty
}
```

### ContentParts Collection
```php
final readonly class ContentParts {
    /** @var ContentPart[] */
    private array $parts;

    public static function empty(): self;
    public static function fromArray(array $parts): self;
    public function add(ContentPart $part): self;
    public function replaceLast(ContentPart $part): self;
    public function first(): ?ContentPart;
    public function last(): ?ContentPart;
    public function get(int $index): ?ContentPart;
    public function count(): int;
    public function toArray(): array;
    public function toString(string $separator = "\n"): string;
    public function withoutEmpty(): self;
}
```

### Content Construction
```php
// Factory methods
Content::empty();                          // No parts
Content::text($text);                      // Single text part
Content::texts(...$texts);                 // Multiple text parts

// Universal constructor
Content::fromAny($input);                  // Handles multiple input types
Content::fromParts($parts);                // From ContentParts collection

// Input resolution logic
match(true) {
    is_null($content) => new self(),
    is_string($content) => new self(ContentPart::text($content)),
    is_array($content) && Message::isMessage($content) => Content::fromAny($content['content'] ?? ''),
    is_array($content) => Content::fromParts(ContentParts::fromArray($content)),
    $content instanceof Content => Content::fromParts($content->partsList()),
    $content instanceof ContentPart => new self($content),
    $content instanceof ContentParts => Content::fromParts($content),
}
```

### Content State Management
```php
// Content introspection
$content->parts();                         // ContentPart[] (deprecated)
$content->partsList();                     // ContentParts collection
$content->toArray();                       // Serialize to array
$content->toString();                      // Extract text content
$content->normalized();                    // string|array based on complexity

// Content mutation (immutable)
$content->addContentPart($part);           // Add new part
$content->appendContentField($key, $value); // Add single field to last part
```

### Content Complexity Logic
```php
// Composite detection (delegates to hasSingleTextContentPart())
isComposite(): bool {
    return match(true) {
        $this->isNull() => false,
        ($this->parts->count() > 1) => true,
        $this->hasSingleTextContentPart() => false,
        default => true,
    };
}

// Normalization based on complexity
normalized(): string|array {
    return match(true) {
        $this->isNull() => '',
        $this->isSimple() => $this->firstContentPart()?->toString() ?? '',
        default => $this->parts->toArray(),
    };
}
```

## ContentPart System

### ContentPart Structure
```php
final readonly class ContentPart {
    protected string $type;                // Content type identifier
    /** @var array<string, mixed> */
    protected array $fields;               // Type-specific data

    // Constructor filters out null/empty values
    public function __construct(string $type, array $fields = []);
}
```

### ContentPart Factory Methods
```php
// Basic types
ContentPart::text($text);                  // Text content part
ContentPart::imageUrl($url);               // Image from URL (simple format)
ContentPart::image($image);                // From Image object (nested OpenAI format)
ContentPart::file($file);                  // From File object
ContentPart::audio($audio);                // From Audio object

// Array construction
ContentPart::fromArray($array);            // Extract type and fields
ContentPart::fromAny($item);               // Universal constructor

// fromAny resolution
match(true) {
    is_string($item) => self::text($item),
    is_array($item) => self::fromArray($item),
    is_object($item) && $item instanceof self => $item,
    is_object($item) && $item instanceof Image => self::image($item),
    is_object($item) && $item instanceof File => self::file($item),
    is_object($item) && $item instanceof Audio => self::audio($item),
    default => throw new InvalidArgumentException('Unsupported content type'),
}
```

### ContentPart API
```php
$part->type();                            // Content type string
$part->fields();                          // All fields array
$part->withFields($fields);               // Replace all fields (immutable)
$part->withField($key, $value);           // Add/update single field (immutable)
$part->get($key, $default);               // Get field value
$part->has($key);                         // Check field existence

// Type checking
$part->isTextPart();                      // type === 'text'
$part->hasText();                         // Has 'text' field
$part->isEmpty();                         // All fields null/empty
$part->isSimple();                        // Single 'text' field only

// Serialization
$part->toArray();                         // Export with type filtering
$part->toString();                        // Extract text or empty string
```

### ContentPart Export Filtering
```php
// Field export logic in toArray()
private function shouldExport(string|int $key, mixed $value): bool {
    return !is_null($value)
        && ($value !== '')
        && ($value !== [])
        && (is_string($key) ? (str_starts_with($key, '_') === false) : true);
}
// Filters: null values, empty strings, empty arrays, underscore-prefixed keys
```

## Media Utilities

### Image Utility Class
```php
class Image implements CanProvideMessages {
    protected string $base64bytes = '';    // Base64 data or empty
    protected string $url = '';            // HTTP URL or empty
    protected string $mimeType;            // MIME type
}

// Construction patterns
Image::fromFile($imagePath);               // Load from file system
Image::fromBase64($base64string, $mimeType); // From base64 data: string (must start with 'data:' prefix)
Image::fromUrl($imageUrl, $mimeType);      // From HTTP URL

// Content integration
$image->toContentPart();                   // ContentPart with OpenAI image_url structure
$image->toContent();                       // Content with single image part
$image->toMessage();                       // Message with user role
$image->toMessages();                      // Messages collection

// Data access
$image->toImageUrl();                      // URL or base64 string
$image->getBase64Bytes();                  // Base64 data
$image->getMimeType();                     // MIME type

// OpenAI format
$image->toContentPart()->toArray() produces:
[
    'type' => 'image_url',
    'image_url' => [
        'url' => $this->url ?: $this->base64bytes
    ]
]

$image->toArray() produces:
[
    'role' => 'user',
    'content' => [
        [
            'type' => 'image_url',
            'image_url' => ['url' => $this->url ?: $this->base64bytes]
        ],
    ],
]
```

### File Utility Class
```php
class File implements CanProvideMessages {
    public function __construct(
        string $fileData = '',             // Base64 data
        string $fileName = '',             // Original filename
        string $fileId = '',               // File identifier (for uploaded files)
        string $mimeType = 'application/octet-stream',
    );
}

// Construction
File::fromFile($filePath);                 // Load from file system
File::fromBase64($base64string, $mimeType); // From base64 data: string (must start with 'data:' prefix)

// Content integration
$file->toContentPart();                    // ContentPart with file structure
$file->toMessage();                        // Message with user role
$file->toMessages();                       // Messages collection

// Data access
$file->getBase64Bytes();                   // Base64 data
$file->getMimeType();                      // MIME type

// ContentPart output structure
$file->toContentPart() produces:
new ContentPart('file', [
    'file' => [
        'file_data' => $this->base64bytes,  // Base64 data if available
        'file_name' => $this->fileName,     // Original filename
        'file_id' => $this->fileId,         // File ID for uploaded files
    ]
])

// Supports both uploaded files (file_id) and inline files (file_data)
// OpenAI API compatible structure
```

### Audio Utility Class
```php
class Audio {
    public function __construct(
        protected string $format,          // Audio format: 'wav', 'mp3', etc.
        protected string $base64bytes,     // Base64 encoded audio data
    );

    // Accessors
    $audio->format();                      // string
    $audio->getBase64Bytes();              // string

    // ContentPart integration
    $audio->toContentPart() produces:
    new ContentPart('input_audio', [
        'input_audio' => [
            'format' => $this->format,
            'data' => $this->base64bytes,
        ]
    ]);

    // OpenAI API compatible input_audio structure
}
```

### Metadata Utility Class
```php
final readonly class Metadata {
    private array $metadata;

    // Construction
    Metadata::empty();                         // Empty metadata
    Metadata::fromArray($array);               // From array
    new Metadata($array);                      // Direct construction

    // Immutable operations
    $metadata->withKeyValue($key, $value);     // Add/update key-value pair
    $metadata->withoutKey($key);               // Remove key

    // Data access
    $metadata->get($key, $default);            // Get value with default
    $metadata->hasKey($key);                   // Check key existence
    $metadata->keys();                         // All keys array
    $metadata->isEmpty();                      // Check if empty
    $metadata->toArray();                      // Convert to array

    // Usage patterns for OpenAI content enhancement
    $imageMetadata = Metadata::empty()
        ->withKeyValue('detail', 'high')
        ->withKeyValue('alt_text', 'Description');

    $audioMetadata = Metadata::fromArray([
        'transcription' => 'Hello world',
        'confidence' => 0.95,
        'language' => 'en'
    ]);

    $fileMetadata = $metadata->withKeyValue('page_count', 42);
}
```

## OpenAI API Content Part Compliance

Canonical output uses nested payload keys for non-text parts (e.g. `image_url`, `file`, `input_audio`). Flat legacy inputs like `['type' => 'image_url', 'url' => '...']` are accepted, but outputs are normalized to the nested shape.

### Supported Content Part Types
```php
// Text content part
[
    'type' => 'text',
    'text' => 'Hello world'
]

// Image content part (URL format)
[
    'type' => 'image_url',
    'image_url' => [
        'url' => 'https://example.com/image.jpg',
        'detail' => 'high'  // Optional: auto, low, high
    ]
]

// Image content part (base64 format)
[
    'type' => 'image_url',
    'image_url' => [
        'url' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ...'
    ]
]

// Audio input content part
[
    'type' => 'input_audio',
    'input_audio' => [
        'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAI...', // Base64
        'format' => 'wav'  // wav, mp3
    ]
]

// File content part (inline)
[
    'type' => 'file',
    'file' => [
        'file_data' => 'data:application/pdf;base64,JVBERi0xLjQ...',
        'file_name' => 'document.pdf'
    ]
]

// File content part (uploaded)
[
    'type' => 'file',
    'file' => [
        'file_id' => 'file-BK7bzQj3FfUp6VNGYLssxKcE',
        'file_name' => 'uploaded_document.pdf'
    ]
]
```

### Multimodal Message Examples
```php
// Complete multimodal message structure
$message = [
    'role' => 'user',
    'content' => [
        [
            'type' => 'text',
            'text' => 'What is in this image and analyze the audio file?'
        ],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/chart.png',
                'detail' => 'high'
            ]
        ],
        [
            'type' => 'input_audio',
            'input_audio' => [
                'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAI...',
                'format' => 'wav'
            ]
        ],
        [
            'type' => 'file',
            'file' => [
                'file_id' => 'file-abc123',
                'file_name' => 'report.pdf'
            ]
        ]
    ]
];

// Using utility classes to build content
$content = new Content(
    ContentPart::text('Analyze this content:'),
    ContentPart::image(Image::fromUrl('https://example.com/image.jpg', 'image/jpeg')),
    ContentPart::audio(new Audio('wav', $base64AudioData)),
    ContentPart::file(File::fromFile('/path/to/document.pdf'))
);

// Enhanced with metadata
$content = $content->appendContentField('analysis_type', 'comprehensive');
```

## MessageStore System Architecture

### MessageStore Structure
```php
final readonly class MessageStore {
    public Sections $sections;                // Collection of named sections
    public Metadata $parameters;              // Key-value parameters
}

// Construction
MessageStore::fromSections(Section ...$sections);
MessageStore::fromMessages(Messages $messages, string $section = 'messages');
MessageStore::fromArray(array $data);         // Deserialize from array

// Section management
$store->sections();                           // Get Sections collection
$store->withSection(string $name);            // Ensure section exists
$store->setSection(Section $section);         // Add or replace a section
$store->removeSection(string $name);          // Remove a section by name
$store->select(string|array $sections);       // Select specific sections
$store->merge(MessageStore $other);           // Merge with another store
$store->withoutEmpty();                       // Remove empty sections

// Serialization
$store->toMessages();                         // Flatten to Messages
$store->toArray();                            // Export structured store array
$store->toFlatArray();                        // Export flat messages array
$store->toString();                           // Text representation
```

### Section Operator API
```php
// Section access and queries
$store->section('system')->exists();         // Check if section exists
$store->section('system')->isEmpty();        // Check if section empty
$store->section('system')->get();            // Get Section object
$store->section('system')->messages();       // Get section messages

// Section mutations (immutable)
$store->section('system')->appendMessages($messages);
$store->section('system')->setMessages($messages);
$store->section('system')->remove();         // Remove entire section
$store->section('system')->clear();          // Clear section messages
```

### Parameter Operator API
```php
// Parameter access and management
$store->parameters()->get();                 // Get all parameters
$store->parameters()->setParameter('key', 'value');
$store->parameters()->unsetParameter('key');
$store->parameters()->mergeParameters($params);
$store->parameters()->withParams($newParams);
```

### Section Class Structure
```php
final readonly class Section implements Countable, IteratorAggregate {
    public string $name;
    public Messages $messages;
}

// Construction
Section::empty('section_name');
Section::fromArray($data);                 // Deserialize from array

// Accessors
$section->name();                          // string
$section->isEmpty();                       // bool
$section->messages();                      // Messages
$section->count();                         // int (Countable)
$section->getIterator();                   // IteratorAggregate

// Mutation (immutable)
$section->appendMessages($messages);
$section->withMessages($messages);
$section->appendContentField($key, $value);

// Transformation
$section->toMergedPerRole();
$section->withoutEmptyMessages();
$section->toArray();
$section->toString();
```

### Sections Collection
```php
final readonly class Sections {
    // Collection management
    public function add(Section ...$sections): Sections;
    public function set(Section ...$sections): Sections;    // Add or replace
    public function has(string $name): bool;
    public function get(string $name): ?Section;
    public function select(array $names): Sections;         // Select by names
    public function filter(callable $callback): Sections;
    public function remove(callable $callback): Sections;   // Remove matching
    public function merge(Sections $other): Sections;       // Merge collections
    public function withoutEmpty(): Sections;                // Filter empty sections
    public function toMessages(): Messages;

    // Iteration and access
    public function all(): array;
    // IteratorAggregate - iterate with foreach ($sections as $section)
    public function count(): int;
    public function names(): array;
    public function map(callable $callback): array;
    public function reduce(callable $callback, mixed $initial): mixed;
}
```

## Messages Collection System

### Messages Class Structure
```php
final readonly class Messages {
    /** @var MessageList $messages */
    private MessageList $messages;

    public function __construct(Message ...$messages);
    public static function empty(): static;
}
```

### MessageList Collection
```php
final readonly class MessageList {
    /** @var Message[] */
    private array $messages;

    public static function empty(): self;
    public static function fromArray(array $messages): self;
    public function all(): array;
    public function add(Message $message): self;
    public function addAll(self $messages): self;
    public function prependAll(self $messages): self;
    public function replaceLast(Message $message): self;
    public function removeHead(): self;
    public function removeTail(): self;
    public function reversed(): self;
    public function withoutEmpty(): self;
    public function first(): ?Message;
    public function last(): ?Message;
    public function get(int $index): ?Message;
    public function count(): int;
    public function isEmpty(): bool;
    public function toArray(): array;
}
```

### Messages Construction Patterns
```php
// Basic construction
new Messages(...$messageArray);
Messages::empty();

// Factory methods
Messages::fromString($content, $role = 'user'); // Single message
Messages::fromArray($messagesArray);            // Array of message arrays
Messages::fromList($messageList);               // MessageList collection
Messages::fromMessages($arrayOrMessages);       // Array of Message objects
Messages::fromAnyArray($mixedArray);           // Mixed array types
Messages::fromAny($input);                     // Universal constructor
Messages::fromInput($input);                   // Input with provider support

// Input resolution in fromAny()
match(true) {
    is_string($messages) => self::fromString($messages),
    is_array($messages) => self::fromAnyArray($messages),
    $messages instanceof Message => new Messages($messages),
    $messages instanceof Messages => $messages,
    $messages instanceof MessageList => Messages::fromList($messages),
}
```

### Messages Access API
```php
// Basic access
$messages->first();                        // First message or empty
$messages->last();                         // Last message or empty
$messages->all();                          // Message[] array (deprecated)
$messages->messageList();                  // MessageList collection
$messages->count();                        // Message count

// Iteration
$messages->each();                         // Generator<Message>
foreach ($messages->each() as $message) { /* */ }

// Collection operations
$messages->map($callback);                 // Transform to array
$messages->filter($callback);              // Filter messages (immutable)
$messages->reduce($callback, $initial);    // Reduce to single value

// Partitioning
$messages->head();                         // First message as array (deprecated)
$messages->tail();                         // Last message as array (deprecated)
$messages->headList();                     // First message as MessageList
$messages->tailList();                     // Last message as MessageList

// State checking
$messages->isEmpty();                      // All messages empty
$messages->notEmpty();                     // Has non-empty messages
$messages->hasComposites();                // Any message is composite

// Role-specific access
$messages->firstRole();                    // MessageRole of first message
$messages->lastRole();                     // MessageRole of last message

// ID-based access
$messages->getById(MessageId $id);         // ?Message
$messages->hasId(MessageId $id);           // bool
```

### Messages Mutation API
```php
// Role-based fluent appending
$messages->asSystem($content);             // Append system message
$messages->asDeveloper($content);          // Append developer message
$messages->asUser($content);               // Append user message
$messages->asAssistant($content);          // Append assistant message
$messages->asTool($content);               // Append tool message

// Message management (immutable)
$messages->withMessage($message);          // Replace with single message
$messages->withMessages($messages);        // Replace all messages
$messages->appendMessage($message);        // Append single message
$messages->appendMessages($messages);      // Append multiple messages
$messages->prependMessages($messages);     // Prepend multiple messages
$messages->removeHead();                   // Remove first message
$messages->removeTail();                   // Remove last message
$messages->appendContentField($key, $value); // Append field to last message content
```

### Messages Transformation API

```php
// Role-based operations
$messages->forRoles($roles);               // Filter by roles
$messages->exceptRoles($roles);            // Exclude roles
$messages->headWithRoles($roles);          // Take while role matches
$messages->tailAfterRoles($roles);         // Skip while role matches
$messages->remapRoles($mapping);           // Transform roles

// Content operations
$messages->contentParts();                 // ContentParts collection from all messages
$messages->toMergedPerRole();              // Merge consecutive same-role messages

// Collection operations
$messages->reversed();                     // Reverse message order
$messages->withoutEmptyMessages();         // Remove empty messages
```

### Messages Serialization
```php
// Array conversion
$messages->toArray();                      // Message array format (filters empty)
$messages->toString($separator = "\n");    // Text with separator (no composites)

// Static conversion utilities
Messages::asString($messageArray, $separator, $renderer); // Custom rendering

// Composite handling
if ($messages->hasComposites()) {
    // toString() throws RuntimeException
    // Use toArray() or custom rendering
}
```

## Advanced Message Operations

### Message State Management
```php
// Message access
$message->role();                          // MessageRole enum
$message->name();                          // Name string
$message->content();                       // Content object
$message->contentParts();                  // ContentParts collection
$message->contentParts()->all();           // ContentPart[] array
$message->parentId();                      // ?MessageId

// Role and type checking
$message->isTool();                        // role === 'tool'
$message->isAssistant();                   // role === 'assistant'
$message->hasRole(MessageRole ...$roles);  // Check against multiple roles
$message->type();                          // MessageType enum (Text, AssistantToolCalls, ToolResult)

// Tool accessors
$message->hasToolCalls();                  // Has non-empty ToolCalls
$message->toolCalls();                     // ToolCalls collection
$message->hasToolResult();                 // Has a ToolResult (not null)
$message->toolResult();                    // ?ToolResult

// State checking
$message->isEmpty();                       // Content empty and no metadata/tool data
$message->isComposite();                   // Complex content structure

// Metadata operations
$message->metadata();                      // Get Metadata object
$message->withMetadata($key, $value);      // Add metadata (immutable)
```

### Message Mutation (Immutable)
```php
$message->withContent($content);           // Replace content
$message->withRole($role);                 // Change role
$message->withName($name);                 // Change name
$message->withToolCalls($toolCalls);       // Replace ToolCalls
$message->withToolResult($toolResult);     // Replace ToolResult
$message->withParentId($parentId);         // Set parent message ID
$message->addContentFrom($sourceMessage); // Merge content from another message
$message->addContentPart($part);           // Add content part
```

### Message Serialization
```php
// Array format (OpenAI compatible)
$message->toArray() produces:
[
    'id' => 'uuid-v4',
    'createdAt' => '2026-02-01T12:00:00+00:00',
    'parentId' => 'uuid-v4',               // Optional
    'role' => $this->role,
    'name' => $this->name,                 // If not empty
    'content' => /* content based on complexity */,
    '_metadata' => $this->metadata,        // If not empty
]

// Content serialization logic
'content' => match(true) {
    $this->content->isEmpty() => '',
    $this->content->isComposite() => $this->content->toArray(),
    default => $this->content->toString(),
}

// Text extraction
$message->toString();                      // Content as string
```

## Message Validation and Detection

### Format Detection
```php
// Message format validation
Message::isMessage($array): bool {
    return isset($array['role']) && (
        isset($array['content']) || isset($array['_metadata'])
    );
}

Message::isMessages($array): bool {
    // All items must be valid messages
    foreach ($array as $message) {
        if (!self::isMessage($message)) return false;
    }
    return true;
}

// State detection utilities
Message::becomesComposite($messageArray);  // Will be composite after parsing
Messages::becomesEmpty($input);            // Will be empty after parsing
Messages::becomesComposite($messageArray); // Contains composite messages
```

## Conversion and Integration Patterns

### Role-Based Merging
```php
// Merge consecutive same-role messages
$merged = $messages->toMergedPerRole();
$array = $merged->toArray();
```

### Provider Pattern Integration
```php
// CanProvideMessage implementations
class CustomClass implements CanProvideMessage {
    public function toMessage(): Message {
        return new Message('user', $this->getText());
    }
}

// CanProvideMessages implementations
class CustomCollection implements CanProvideMessages {
    public function toMessages(): Messages {
        return Messages::fromArray($this->getData());
    }
}

// Usage in factory methods
Messages::fromInput($input) handles:
- Messages => direct return
- CanProvideMessages => $input->toMessages()
- Message => wrap in Messages
- CanProvideMessage => $input->toMessage() -> wrap
- default => TextRepresentation::fromAny($input) -> wrap
```

### TextRepresentation Integration
```php
// Used for arbitrary input conversion
Message::fromInput($input, $role) uses:
match(true) {
    $input instanceof Message => $input,
    $input instanceof CanProvideMessage => $input->toMessage(),
    default => new Message($role, TextRepresentation::fromAny($input)),
}

Messages::fromInput($input) uses similar pattern for Messages
```

## Performance and Memory Considerations

### Immutable Design Patterns
```php
// Core value objects use immutable operations
$newMessage = $message->withRole('assistant');     // Creates new instance
$newContent = $content->addContentPart($part);     // Creates new instance
$newMessages = $messages->appendMessage($message); // Creates new instance
```

### Lazy Evaluation Patterns
```php
// Content complexity calculated on demand
$content->isComposite();                          // Evaluates state rules
$content->normalized();                           // Returns appropriate format

// Message state evaluation
$message->isEmpty();                              // Checks content + metadata
$message->isComposite();                          // Delegates to content

// Collection operations with generators
foreach ($messages->each() as $message) {          // Generator-based iteration
    // Process one at a time
}
```

### Memory-Efficient Operations
```php
// Field filtering in ContentPart constructor and export
$fields = array_filter($fields, fn($value, $key) =>
    !is_null($value) && ($value !== []), ARRAY_FILTER_USE_BOTH
);

// Messages::toArray() preserves the collection as-is
foreach ($this->messages as $message) {
    $result[] = $message->toArray();
}

// Efficient role-based operations
$messages->forRoles($roles);                      // Creates new Messages without cloning filtered messages
```
