# Messages Package - Deep Reference

## Core Architecture

### MessageStore System

The MessageStore system provides multi-section message management for complex conversational scenarios:

```php
class MessageStore {
    public Sections $sections;
    public MessageStoreParameters $parameters;
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
}
```

## Message Class Structure

### Core Message Class
```php
final readonly class Message {
    protected string $role;
    protected string $name;
    protected Content $content;
    protected Metadata $metadata;
    
    public const DEFAULT_ROLE = 'user';
}
```

### Message Construction Patterns
```php
// Basic construction
new Message($role, $content, $name = '', $metadata = []);
new Message(MessageRole::User, 'Hello world');
new Message('', 'Content');  // Defaults to 'user' role

// Factory methods
Message::empty();                          // Empty message
Message::make($role, $content, $name);     // Explicit construction
Message::asUser($message);                 // Force user role
Message::asAssistant($message);            // Force assistant role
Message::asSystem($message);               // Force system role
Message::asDeveloper($message);            // Force developer role

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
    $message instanceof Message => $message->clone(),
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
```

## Content System Architecture

### Content Class Structure
```php
final readonly class Content {
    protected ContentParts $parts;

    // Content state classification
    public function isComposite(): bool;    // Multi-part or complex content
    public function isEmpty(): bool;        // All parts empty
    public function isNull(): bool;         // No parts
    public function isSimple(): bool;       // Single simple text part
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
    public function toString(string $separator = \"\\n\"): string;
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
$content->firstContentPart();              // First part or null
$content->lastContentPart();               // Last part (never null)
$content->toArray();                       // Serialize to array
$content->toString();                      // Extract text content
$content->normalized();                    // string|array based on complexity

// Content mutation (immutable)
$content->addContentPart($part);           // Add new part
$content->appendContentField($key, $value); // Add single field to last part
$content->clone();                         // Deep clone
```

### Content Complexity Logic
```php
// Composite detection
isComposite(): bool {
    return match(true) {
        $this->isNull() => false,
        (count($this->parts) > 1) => true,
        (count($this->parts) === 1) && 
        ($this->firstContentPart()?->isTextPart() ?? true) && 
        ($this->firstContentPart()?->isSimple() ?? true) => false,
        default => true,
    };
}

// Normalization based on complexity
normalized(): string|array {
    return match(true) {
        $this->isNull() => '',
        $this->isSimple() => $this->firstContentPart()?->toString() ?? '',
        default => array_map(fn(ContentPart $part) => $part->toArray(), $this->parts),
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
$part->clone();                          // Deep clone
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
Image::fromBase64($base64string, $mimeType); // From base64 string
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
    protected string $base64bytes = '';    // Base64 data
    protected string $mimeType;            // MIME type
    protected string $fileId = '';         // File identifier
    protected string $fileName = '';       // Original filename
}

// Construction
File::fromFile($filePath);                 // Load from file system
File::fromBase64($base64string, $mimeType); // From base64 string

// ContentPart integration  
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
    protected string $format;              // Audio format
    protected string $base64bytes;         // Base64 audio data

    // ContentPart integration
    $audio->toContentPart() produces:
    new ContentPart('input_audio', [
        'input_audio' => [
            'format' => $this->format,  // 'wav', 'mp3', etc.
            'data' => $this->base64bytes,  // Base64 encoded audio data
        ]
    ]);
    
    // OpenAI API compatible input_audio structure
    // Supports wav, mp3, and other audio formats
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
    public MessageStoreParameters $parameters; // Key-value parameters
}

// Construction
MessageStore::fromSections(Section ...$sections);
MessageStore::fromMessages(Messages $messages, string $section = 'messages');

// Section management
$store->withSection(string $name);            // Ensure section exists
$store->select(string|array $sections);      // Select specific sections
$store->toMessages();                         // Flatten to Messages
$store->toArray();                           // Export messages array
$store->toString();                          // Text representation
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
final readonly class Section {
    public string $name;
    public Messages $messages;
}

// Usage
Section::empty('section_name');
$section->appendMessages($messages);
$section->withMessages($messages);
$section->appendContentField($key, $value);
$section->toMergedPerRole();
$section->withoutEmptyMessages();
```

### Sections Collection
```php
final readonly class Sections {
    // Collection management
    public function add(Section ...$sections): Sections;
    public function has(string $name): bool;
    public function get(string $name): ?Section;
    public function filter(callable $callback): Sections;
    public function toMessages(): Messages;
    
    // Iteration and access
    public function all(): array;
    public function each(): iterable;
    public function count(): int;
    public function names(): array;
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
$messages->withoutEmptyMessages();                      // Remove empty messages
$messages->clone();                        // Deep clone all messages
```

### Messages Serialization
```php
// Array conversion
$messages->toArray();                      // Message array format (filters empty)
$messages->toString($separator = "\n");    // Text with separator (no composites)

// Static conversion utilities
Messages::asPerRoleArray($messageArray);   // Merge same-role messages
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

// State checking
$message->isEmpty();                       // Content empty and no metadata
$message->isComposite();                   // Complex content structure

// Metadata operations
$message->metadata();                      // Get Metadata object
$message->withMetadata($key, $value);      // Add metadata (immutable)
```

### Message Mutation (Immutable)
```php
$message->withContent($content);           // Replace content
$message->withRole($role);                 // Change role
$message->addContentFrom($sourceMessage); // Merge content from another message
$message->addContentPart($part);           // Add content part
$message->clone();                         // Deep clone
```

### Message Serialization
```php
// Array format (OpenAI compatible)
$message->toArray() produces:
[
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

Message::hasRoleAndContent($array): bool {
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

### Array Type Detection
```php
// Used in Message::fromArray() for input classification
private static function isArrayOfStrings(array $array): bool;
private static function isArrayOfMessageArrays(array $array): bool;
private static function isArrayOfContent(array $array): bool;
private static function isArrayOfMessages(array $array): bool;
private static function isArrayOfContentParts(array $array): bool;
```

## Conversion and Integration Patterns

### Role-Based Merging
```php
// Messages::asPerRoleArray() logic
public static function asPerRoleArray(array $messages): array {
    $role = 'user';
    $merged = Messages::empty();
    $content = [];
    
    foreach ($messages as $message) {
        if ($role !== $message['role'] || Message::becomesComposite($message)) {
            // Flush accumulated content
            $merged = $merged->appendMessage(new Message(
                role: $role,
                content: implode("\n\n", array_filter($content)),
            ));
            
            // Handle composite messages separately
            if (Message::becomesComposite($message)) {
                $merged = $merged->appendMessage($message);
                continue;
            }
            
            // Start new role group
            $role = $message['role'];
            $content = [];
        }
        $content[] = $message['content'];
    }
    
    // Flush remaining content
    return $merged->toArray();
}
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
// All classes are readonly with immutable operations
$newMessage = $message->withRole('assistant');     // Creates new instance
$newContent = $content->addContentPart($part);     // Creates new instance
$newMessages = $messages->appendMessage($message); // Creates new instance

// Clone operations preserve deep structure
$clonedMessage = $message->clone();                // Deep clones Content and parts
$clonedMessages = $messages->clone();              // Deep clones all messages
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

// Empty message filtering in Messages::toArray()
foreach ($this->messages as $message) {
    if ($message->isEmpty()) continue;             // Skip empty messages
    $result[] = $message->toArray();
}

// Efficient role-based operations
$messages->forRoles($roles);                      // Creates new Messages without cloning filtered messages
```
