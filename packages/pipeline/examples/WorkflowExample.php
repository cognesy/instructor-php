<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cognesy\Pipeline\Middleware\TimingMiddleware;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\Tags\TimingTag;
use Cognesy\Pipeline\Workflow\Workflow;

echo "🔄 Workflow Example: E-commerce Order Processing\n";
echo "===============================================\n\n";

// Sample order data
$orderData = [
    'id' => 'ORD-12345',
    'customer_id' => 'CUST-567',
    'items' => [
        ['product' => 'Widget A', 'price' => 25.00, 'quantity' => 2],
        ['product' => 'Widget B', 'price' => 15.00, 'quantity' => 1],
    ],
    'total' => 1500.00, // This will cause payment failure
    'payment_method' => 'credit_card'
];

// Define pipeline components
$validationPipeline = Pipeline::for($orderData)
    ->withMiddleware(TimingMiddleware::for('validation'))
    ->through(function($order) {
        echo "  🔍 Validating order format...\n";
        if (!isset($order['id'], $order['customer_id'], $order['items'])) {
            throw new \InvalidArgumentException('Invalid order format');
        }
        return [...$order, 'validated' => true];
    })
    ->through(function($order) {
        echo "  👤 Validating customer...\n";
        if (empty($order['customer_id'])) {
            throw new \InvalidArgumentException('Customer ID required');
        }
        return [...$order, 'customer_validated' => true];
    });

$inventoryPipeline = Pipeline::for($orderData)
    ->withMiddleware(TimingMiddleware::for('inventory'))
    ->through(function($order) {
        echo "  📦 Checking inventory availability...\n";
        // Simulate inventory check
        foreach ($order['items'] as $item) {
            if ($item['quantity'] > 10) {
                throw new \RuntimeException("Insufficient stock for {$item['product']}");
            }
        }
        return [...$order, 'inventory_reserved' => true];
    });

$paymentPipeline = Pipeline::for($orderData)
    ->withMiddleware(TimingMiddleware::for('payment'))
    ->through(function($order) {
        echo "  💳 Processing payment...\n";
        if ($order['total'] > 1000) {
            throw new \RuntimeException('Payment amount too high, requires manual approval');
        }
        return [...$order, 'payment_processed' => true];
    });

$fulfillmentPipeline = Pipeline::for($orderData)
    ->withMiddleware(TimingMiddleware::for('fulfillment'))
    ->through(function($order) {
        echo "  📋 Creating order record...\n";
        return [...$order, 'order_created' => true];
    })
    ->through(function($order) {
        echo "  📧 Sending confirmation email...\n";
        return [...$order, 'confirmation_sent' => true];
    });

$auditPipeline = Pipeline::for($orderData)
    ->through(function($order) {
        echo "  📝 Logging order processing for audit...\n";
        // This is a side effect - doesn't modify the order
        return $order;
    });

// Compose the complete order processing workflow
echo "Creating order processing workflow...\n\n";

$orderWorkflow = Workflow::empty()
    ->through($validationPipeline)
    ->when(
        fn($computation) => $computation->result()->isSuccess(),
        $inventoryPipeline
    )
    ->when(
        fn($computation) => $computation->result()->isSuccess() && $computation->result()->unwrap()['total'] > 50,
        $paymentPipeline  // Only process payment for orders > $50
    )
    ->through($fulfillmentPipeline)
    ->tap($auditPipeline);  // Side effect - always execute for audit

// Execute the workflow
echo "Processing order {$orderData['id']}...\n";
echo "────────────────────────────────────────\n";

$result = $orderWorkflow->process($orderData);

echo "────────────────────────────────────────\n";

if ($result->isSuccess()) {
    echo "✅ Order processed successfully!\n\n";
    
    $finalOrder = $result->value();
    echo "Final order status:\n";
    echo "  - Validated: " . ($finalOrder['validated'] ? '✅' : '❌') . "\n";
    echo "  - Customer validated: " . ($finalOrder['customer_validated'] ? '✅' : '❌') . "\n";
    echo "  - Inventory reserved: " . ($finalOrder['inventory_reserved'] ? '✅' : '❌') . "\n";
    echo "  - Payment processed: " . ($finalOrder['payment_processed'] ? '✅' : '❌') . "\n";
    echo "  - Order created: " . ($finalOrder['order_created'] ? '✅' : '❌') . "\n";
    echo "  - Confirmation sent: " . ($finalOrder['confirmation_sent'] ? '✅' : '❌') . "\n";
} else {
    echo "❌ Order processing failed!\n";
    echo "Error: " . $result->exception()->getMessage() . "\n";
}

// Show timing information from all pipeline stages
echo "\nTiming Information:\n";
echo "─────────────────────\n";
$timings = $result->computation()->all(TimingTag::class);
$totalTime = 0;

foreach ($timings as $timing) {
    echo "  " . $timing->summary() . "\n";
    $totalTime += $timing->duration;
}

echo "  Total processing time: " . number_format($totalTime * 1000, 2) . "ms\n";

echo "\n🎉 Workflow example completed!\n";