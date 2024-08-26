---
title: 'Image to data (OpenAI)'
docname: 'image_to_data'
---

## Overview

This is an example of how to extract structured data from an image using
Instructor. The image is loaded from a file and converted to base64 format
before sending it to OpenAI API.

The response model is a PHP class that represents the structured receipt
information with data of vendor, items, subtotal, tax, tip, and total.


## Scanned image

Here's the image we're going to extract data from.

![Receipt](images/receipt.png)


## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Extras\Image\Image;
use Cognesy\Instructor\Instructor;

class Vendor {
    public ?string $name = '';
    public ?string $address = '';
    public ?string $phone = '';
}

class ReceiptItem {
    public string $name;
    public ?int $quantity = 1;
    public float $price;
}

class Receipt {
    public Vendor $vendor;
    /** @var ReceiptItem[] */
    public array $items = [];
    public ?float $subtotal;
    public ?float $tax;
    public ?float $tip;
    public float $total;
}

$receipt = (new Instructor)->respond(
    input: Image::fromFile(__DIR__ . '/receipt.png'),
    responseModel: Receipt::class,
    prompt: 'Extract structured data from the receipt.',
    options: ['max_tokens' => 4096]
);

dump($receipt);

assert($receipt->total === 169.82);
?>
```
