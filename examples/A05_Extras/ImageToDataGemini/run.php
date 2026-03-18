---
title: 'Image to data (Gemini)'
docname: 'image_to_data_gemini'
id: '22c8'
tags:
  - 'extras'
  - 'vision'
  - 'gemini'
---
## Overview

This is an example of how to extract structured data from an image using
Instructor. The image is loaded from a file and converted to base64 format
before sending it to OpenAI API.

The response model is a PHP class that represents the structured receipt
information with data of vendor, items, subtotal, tax, tip, and total.


## Scanned image

Here's the image we're going to extract data from.

![Receipt](/images/receipt.png)


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Image\Image;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
class Vendor {
    public ?string $name = '';
    public ?string $address = '';
    public ?string $phone = '';
}

class ReceiptItem {
    public string $name;
    public ?int $quantity = 1;
    public float|int $price;
}

class Receipt {
    public Vendor $vendor;
    /** @var ReceiptItem[] */
    public array $items = [];
    public float|int|null $subtotal;
    public float|int|null $tax;
    public float|int|null $tip;
    public float|int $total;
}

$receipt = new StructuredOutput(
    StructuredOutputRuntime::fromProvider(LLMProvider::using('gemini'))
        ->withOutputMode(OutputMode::Json)
)->with(
    messages: Image::fromFile(__DIR__ . '/receipt.png')->toMessage(),
    responseModel: Receipt::class,
    prompt: 'Extract structured data from the receipt. Return result as JSON following this schema: <|json_schema|>',
    options: ['max_tokens' => 4096]
)->get();

dump($receipt);

assert(is_numeric($receipt->total));
?>
```
