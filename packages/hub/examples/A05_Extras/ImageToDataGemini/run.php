<?php
require 'examples/boot.php';

use Cognesy\Addons\Image\Image;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

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

$receipt = (new StructuredOutput)->using('gemini')->with(
    messages: Image::fromFile(__DIR__ . '/receipt.png')->toMessage(),
    responseModel: Receipt::class,
    prompt: 'Extract structured data from the receipt. Return result as JSON following this schema: <|json_schema|>',
    mode: OutputMode::Json,
    options: ['max_tokens' => 4096]
)->get();

dump($receipt);

assert(is_numeric($receipt->total));
?>
