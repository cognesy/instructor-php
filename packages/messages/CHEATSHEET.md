# Messages Package - Deep Reference

## Core Architecture

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
    protected array $metadata;
    
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
    /** @var ContentPart[] */
    protected array $parts;
    
    // Content state classification
    public function isComposite(): bool;    // Multi-part or complex content
    public function isEmpty(): bool;        // All parts empty
    public function isNull(): bool;         // No parts
    public function isSimple(): bool;       // Single simple text part
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

// Input resolution logic
match(true) {
    is_null($content) => new self(),
    is_string($content) => new self(ContentPart::text($content)),
    is_array($content) && Message::isMessage($content) => new self(ContentPart::fromAny($content['content'] ?? '')),
    is_array($content) && Arrays::hasOnlyStrings($content) => /* create text parts */,
    is_array($content) => /* create parts from array items */,
    $content instanceof Content => new self(...$content->parts()),
    $content instanceof ContentPart => new self(...[$content]),
}
```

### Content State Management
```php
// Content introspection
$content->parts();                         // ContentPart[]
$content->firstContentPart();              // First part or null
$content->lastContentPart();               // Last part (never null)
$content->toArray();                       // Serialize to array
$content->toString();                      // Extract text content
$content->normalized();                    // string|array based on complexity

// Content mutation (immutable)
$content->addContentPart($part);           // Add new part
$content->appendContentFields($fields);    // Add fields to last part
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
ContentPart::imageUrl($url);              // Image from URL
ContentPart::image($image);               // From Image object
ContentPart::file($file);                 // From File object  
ContentPart::audio($audio);               // From Audio object

// Array construction
ContentPart::fromArray($array);           // Extract type and fields
ContentPart::fromAny($item);              // Universal constructor

// fromAny resolution
match(true) {
    is_string($item) => self::text($item),
    is_array($item) => self::fromArray($item),
    is_object($item) && $item instanceof self => $item,
    is_object($item) && $item instanceof Image => self::image($item),
    is_object($item) && $item instanceof File => self::file($item),
    is_object($item) && $item instanceof Audio => self::audio($item),
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
$image->toContentPart();                   // ContentPart with image_url type
$image->toContent();                       // Content with single image part
$image->toMessage();                       // Message with user role
$image->toMessages();                      // Messages collection

// Data access
$image->toImageUrl();                      // URL or base64 string
$image->getBase64Bytes();                  // Base64 data
$image->getMimeType();                     // MIME type

// OpenAI format
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
        'file_data' => $this->base64bytes,
        'file_name' => $this->fileName,
        'file_id' => $this->fileId,
    ]
])
```

### Audio Utility Class
```php
class Audio {
    protected string $format;              // Audio format
    protected string $base64bytes;         // Base64 audio data

    // ContentPart integration
    $audio->toContentPart() produces:
    new ContentPart('input_audio', ['input_audio' => [
        'format' => $this->format,
        'data' => $this->base64bytes,
    ]]);
}
```

## Messages Collection System

### Messages Class Structure
```php
final readonly class Messages {
    /** @var Message[] $messages */
    private array $messages;
    
    public function __construct(Message ...$messages);
    public static function empty(): static;
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
}
```

### Messages Access API
```php
// Basic access
$messages->first();                        // First message or empty
$messages->last();                         // Last message or empty
$messages->all();                          // Message[] array
$messages->count();                        // Message count

// Iteration
$messages->each();                         // Generator<Message>
foreach ($messages->each() as $message) { /* */ }

// Collection operations
$messages->map($callback);                 // Transform to array
$messages->filter($callback);              // Filter messages (immutable)
$messages->reduce($callback, $initial);    // Reduce to single value

// Partitioning
$messages->head();                         // First message as array
$messages->tail();                         // Last message as array


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
$messages->contentParts();                 // All ContentPart[] from all messages
$messages->toMergedPerRole();              // Merge consecutive same-role messages

// Collection operations
$messages->reversed();                     // Reverse message order
$messages->trimmed();                      // Remove empty messages
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
$message->contentParts();                  // ContentPart[] array
$message->lastContentPart();               // Last part (never null)
$message->firstContentPart();              // First part or null

// State checking
$message->isEmpty();                       // Content empty and no metadata
$message->isNull();                        // Empty role, content, and metadata
$message->isComposite();                   // Complex content structure

// Metadata operations
$message->hasMeta($key = null);            // Check metadata existence
$message->meta($key = null);               // Get metadata value(s)
$message->metadata($key = null);           // Alias for meta()
$message->metaKeys();                      // Metadata keys array
$message->withMeta($key, $value);          // Add metadata (immutable)
$message->withMetadata($key, $value);      // Alias for withMeta()
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