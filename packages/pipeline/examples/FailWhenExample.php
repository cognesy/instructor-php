<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Pipeline;

echo "🚫 FailWhen Example: Order Validation\n";
echo "====================================\n\n";

// Sample order data
$orders = [
    ['id' => 'ORD-001', 'total' => 150.00, 'customer_tier' => 'premium'],
    ['id' => 'ORD-002', 'total' => -50.00, 'customer_tier' => 'standard'],  // Invalid negative total
    ['id' => 'ORD-003', 'total' => 25000.00, 'customer_tier' => 'standard'], // Too high for standard customer
    ['id' => 'ORD-004', 'total' => 75.00, 'customer_tier' => 'premium'],
];

$processOrder = Pipeline::empty()
    // Validation rules using failWhen
    ->failWhen(
        fn($state) => $state->value()['total'] < 0,
        'Order total cannot be negative'
    )
    ->failWhen(
        fn($state) => $state->value()['total'] > 10000 && $state->value()['customer_tier'] !== 'premium',
        'High-value orders require premium customer tier'
    )
    ->failWhen(
        fn($state) => empty($state->value()['id']),
        'Order ID is required'
    )
    
    // Business logic processors
    ->through(function($order) {
        echo "  ✅ Processing order {$order['id']}...\n";
        return [...$order, 'processed' => true];
    })
    ->through(function($order) {
        $discount = $order['customer_tier'] === 'premium' ? 0.10 : 0.05;
        $finalTotal = $order['total'] * (1 - $discount);
        echo "  💰 Applied {$order['customer_tier']} discount: \${$finalTotal}\n";
        return [...$order, 'final_total' => $finalTotal];
    });

foreach ($orders as $order) {
    echo "Processing Order {$order['id']} (\${$order['total']}, {$order['customer_tier']}):\n";
    echo "─────────────────────────────────────────\n";
    
    $result = $processOrder
        ->create()
        ->for($order);
    
    if ($result->isSuccess()) {
        $processedOrder = $result->value();
        echo "  ✅ Order processed successfully!\n";
        echo "  📦 Final total: \${$processedOrder['final_total']}\n";
    } else {
        echo "  ❌ Order failed validation!\n";
        echo "  🚫 Reason: {$result->exception()->getMessage()}\n";
    }
    
    echo "\n";
}

echo "🎉 FailWhen example completed!\n\n";

echo "💡 Key Benefits:\n";
echo "  • Early validation: Stop processing as soon as a condition fails\n";
echo "  • Clear error messages: Custom failure messages for each condition\n";
echo "  • Composable: Chain multiple validation rules\n";
echo "  • Clean separation: Validation logic separate from business logic\n";