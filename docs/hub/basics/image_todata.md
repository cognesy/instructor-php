# Image to data

This is an example of how to extract structured data from an image using
Instructor. The image is loaded from a file and converted to base64 format
before sending it to OpenAI API.

The response model is a PHP class that represents the structured receipt
information with data of vendor, items, subtotal, tax, tip, and total.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

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

$imagePath = __DIR__ . '/receipt.png';
// load image and convert to base64
$imageBase64 = base64_encode(file_get_contents($imagePath));
// add base 64 prefix
$imageBase64 = 'data:image/png;base64,' . $imageBase64;

$input = [
    ['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Extract data from attached receipt'],
        // ['type' => 'image_url', 'image_url' => 'https://www.inogic.com/blog/wp-content/uploads/2020/09/Receipt-Processor-AI-Builder-in-Canvas-App-9.png'],
        ['type' => 'image_url', 'image_url' => $imageBase64],
    ]],
];

$receipt = (new Instructor)->respond(
    messages: $input,
    responseModel: Receipt::class,
    //options: ['debug' => true],
    model: 'gpt-4-vision-preview',
    options: ['max_tokens' => 4096]
);

dump($receipt);
?>
```
