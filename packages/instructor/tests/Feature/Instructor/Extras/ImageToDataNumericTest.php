<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\MockHttp;

final class Vendor
{
    public ?string $name = null;
    public ?string $address = null;
    public ?string $phone = null;
}

final class ReceiptItem
{
    public string $name;
    public ?int $quantity = 1;
    public float|int $price;
}

final class Receipt
{
    public Vendor $vendor;
    /** @var ReceiptItem[] */
    public array $items = [];
    public float|int|null $subtotal;
    public float|int|null $tax;
    public float|int|null $tip;
    public float|int $total;
}

it('accepts integer and float amounts in receipt extraction', function () {
    $json = <<<'JSON'
{"vendor":{"name":"Geo Restaurant","address":"300 72th Street","phone":"305-864-5586"},"items":[{"name":"Ferrari Carano","price":47},{"name":"Insalat Ceseare","price":7.5}],"subtotal":54.5,"tax":5,"tip":0,"total":59.5}
JSON;

    $mockHttp = MockHttp::get([$json]);
    $receipt = (new StructuredOutput)
        ->withHttpClient($mockHttp)
        ->with(
            messages: [['role' => 'user', 'content' => 'Extract receipt data.']],
            responseModel: Receipt::class,
        )
        ->get();

    expect($receipt)->toBeInstanceOf(Receipt::class);
    expect($receipt->items)->toBeArray();
    expect($receipt->items[0])->toBeInstanceOf(ReceiptItem::class);
    expect($receipt->items[0]->price)->toEqual(47);
    expect($receipt->items[1]->price)->toEqual(7.5);
    expect($receipt->total)->toEqual(59.5);
});
