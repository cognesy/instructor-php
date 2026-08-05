# Messages

Utilities for representing chat messages, multimodal content parts, and message stores. The API is immutable and designed for composing OpenAI-compatible message payloads.

## Canonical content-part shape

Non-text parts are emitted in nested form. Legacy flat inputs are accepted and normalized on output.
Content stores parts in a `ContentParts` collection. Use `partsList()` if you need the value object.
You can also build content directly from a `ContentParts` collection via `Content::fromParts()`.
`parts()` remains for backward compatibility but is deprecated.

Messages now use an internal `MessageList` collection for immutable operations while keeping the public API unchanged. Use `messageList()` if you need the value object.
You can construct a `Messages` instance from a `MessageList` via `Messages::fromList()`.
Use `headList()` / `tailList()` when you need MessageList for partitions.
`head()` and `tail()` remain for backward compatibility but are deprecated.
`all()` remains for backward compatibility but is deprecated.

```php
// text
['type' => 'text', 'text' => 'hello']

// image
['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/image.jpg']]

// audio
['type' => 'input_audio', 'input_audio' => ['data' => '...base64...', 'format' => 'wav']]

// file
['type' => 'file', 'file' => ['file_data' => 'data:...base64...', 'file_name' => 'report.pdf', 'file_id' => 'file-...']]
```

## Quick example

```php
use Cognesy\Messages\Content;
use Cognesy\Messages\Message;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\MessageSessionId;
use Cognesy\Messages\Utils\Image;
use Cognesy\Messages\MessageStore\Storage\InMemoryStorage;

$message = new Message('user', Content::text('Describe this image:'));
$message = $message->addContentPart(ContentPart::image(Image::fromUrl('https://example.com/cat.jpg', 'image/jpeg')));

$messageId = $message->id(); // MessageId value object
$messageIdString = $messageId->toString(); // boundary serialization

$payload = $message->toArray();

$storage = new InMemoryStorage();
$sessionId = $storage->createSession(MessageSessionId::generate());
$storage->append($sessionId, 'messages', $message);
```

## Migration notes (2026-01-05)

- Non-text content parts are now emitted in nested form (e.g. `image_url`, `file`, `input_audio`). Flat legacy inputs are still accepted but normalized on output.
- File payloads use `file_name` (nested under `file`) as the canonical key.
- `Messages::filter()` with no callback now returns all non-empty messages (previously it returned an empty collection).

## Migration notes (2026-08-05)

- `Messages::filter($predicate)` now keeps exactly the messages the predicate accepts. It no longer also drops empty messages, so `filter(fn($m) => $m->isEmpty())` selects them instead of returning nothing. Compose with `withoutEmptyMessages()` if you want both. Calling `filter()` with no argument is deprecated - it is an alias for `withoutEmptyMessages()` and will be removed.
- `Messages::withMessage()` was removed. Use `appendMessage()` to add one message, or `withMessages([$message])` to replace the collection with one.
- Message roles are validated at construction. `new Message('wizard', ...)` and `withRole('wizard')` now throw `InvalidArgumentException` instead of storing an unknown role that failed later, or round-tripped through storage undetected. `''` and `null` still mean the default role.
- Empty fields are omitted from `file` and `input_audio` payloads rather than emitted as `""`, since providers pass such keys through verbatim. A file referenced only by `file_id` no longer carries an empty `file_data`/`file_name`.
- `File`'s constructor rejects a non-empty `$fileData` that is not a `data:` URI, matching what `File::fromBase64()` already did. `''` still means "no inline data".
- `File::fromFile()` now also sets `fileName` from the path's basename. `File::fromFile()` and `Image::fromFile()` throw `RuntimeException` when the MIME type cannot be detected.
- `Audio::getByte64Bytes()` (deprecated typo alias) was removed. Use `getBase64Bytes()`.
- `ContentParts` is now `IteratorAggregate`, so `foreach ($parts as $part)` works directly and `->all()` is no longer needed to iterate.
- A keyed (non-list) content array is now treated as ONE content part instead of being iterated as a parts collection, so `['type' => 'image_url', 'image_url' => [...]]` resolves to a single image part. A parts collection must be a list.
- `Message::withMergedFrom()` is new: it folds another message in (content, tool calls, metadata) while keeping the receiver's identity and role. It throws if either message carries a tool result.
- `Messages::toMergedPerRole()` preserves the first message of each run's `id`/`parentId`/`createdAt`, carries tool calls through the merge, and never merges a message carrying a `ToolResult` with a neighbour.
- `ToolCall::isNone()` is new. Use it to detect the `ToolCall::none()` sentinel; the sentinel name is private and must not be compared directly.
