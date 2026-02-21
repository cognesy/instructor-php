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
