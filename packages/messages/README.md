# Messages

Utilities for representing chat messages, multimodal content parts, and message stores. The API is immutable and designed for composing OpenAI-compatible message payloads.

## Canonical content-part shape

Non-text parts are emitted in nested form. Legacy flat inputs are accepted and normalized on output.

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
use Cognesy\Messages\Utils\Image;

$message = new Message('user', Content::text('Describe this image:'));
$message = $message->addContentPart(ContentPart::image(Image::fromUrl('https://example.com/cat.jpg', 'image/jpeg')));

$payload = $message->toArray();
```
