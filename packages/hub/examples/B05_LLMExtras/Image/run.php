---
title: 'Using images in prompts'
docname: 'image_data'
---

## Overview

`Image` class in Instructor PHP provides an easy way to include images in your prompts.
It supports loading images from files, URLs, or base64 encoded strings. The image can
be sent as part of the message content to the LLM.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Messages\Messages;
use Cognesy\Messages\Utils\Image;
use Cognesy\Polyglot\Inference\Inference;

$messages = (new Messages)
    ->asSystem('You are an expert in car damage assessment.')
    ->asUser([
        'Describe the car damage in the image.',
        Image::fromFile(__DIR__ . '/car-damage.jpg')->toContentPart(),
    ]);

$response = (new Inference)
    ->using('openai')
    ->withModel('gpt-4o')
    ->withMessages($messages)
    ->get();

echo "Response: " . $response . "\n";
