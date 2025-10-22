<?php

use Cognesy\Addons\Image\Image;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;

it('should properly convert Image to Messages with prompt', function () {
    // Create an image with a prompt
    $imagePath = __DIR__ . '/../../examples/A05_Extras/ImageCarDamage/car-damage.jpg';
    
    // Skip test if image file doesn't exist
    if (!file_exists($imagePath)) {
        $this->markTestSkipped('Test image file not found');
    }
    
    $image = Image::fromFile($imagePath);
    
    // Test that toMessages() returns a Messages object
    $messages = $image->toMessages();
    expect($messages)->toBeInstanceOf(Messages::class);
    
    // Test that the messages can be converted to array
    $messagesArray = $messages->toArray();
    expect($messagesArray)->toBeArray();
    expect($messagesArray)->not->toBeEmpty();
    
    // Check that the first message has the expected structure
    $firstMessage = $messagesArray[0] ?? null;
    expect($firstMessage)->not->toBeNull();
    expect($firstMessage)->toHaveKey('role');
    expect($firstMessage)->toHaveKey('content');
    expect($firstMessage['role'])->toBe('user');
});

it('should include prompt text in the message content', function () {
    // Create a mock base64 image data
    $mockImageData = 'data:image/jpeg;base64,/9j/4AAQSkZJRg=='; // minimal valid JPEG header
    
    // Create image with custom prompt
    $prompt = 'Analyze this image for damage';
    $image = new Image($mockImageData, 'image/jpeg', $prompt);
    
    // Get the array representation
    $imageArray = $image->toArray();
    
    // Verify structure
    expect($imageArray)->toHaveKey('role');
    expect($imageArray)->toHaveKey('content');
    expect($imageArray['role'])->toBe('user');
    expect($imageArray['content'])->toBeArray();
    
    // Check that content has both text and image
    $hasText = false;
    $hasImage = false;
    foreach ($imageArray['content'] as $part) {
        if ($part['type'] === 'text') {
            $hasText = true;
            expect($part['text'])->toBe($prompt);
        }
        if ($part['type'] === 'image_url') {
            $hasImage = true;
        }
    }
    
    expect($hasText)->toBeTrue();
    expect($hasImage)->toBeTrue();
});

it('should create valid Messages object from Image', function () {
    // Create a mock base64 image data
    $mockImageData = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
    
    $prompt = 'Test prompt for image';
    $image = new Image($mockImageData, 'image/jpeg', $prompt);
    
    // Convert to Messages
    $messages = $image->toMessages();
    
    // Convert to array for API
    $messagesArray = $messages->toArray();
    
    // This should not be empty - this is what's sent as 'messages' param
    expect($messagesArray)->not->toBeEmpty();
    expect($messagesArray)->toBeArray();
    
    // Verify the structure matches what OpenAI expects
    $firstMessage = $messagesArray[0];
    expect($firstMessage)->toHaveKey('role');
    expect($firstMessage)->toHaveKey('content');
    
    // Content should be an array of parts
    expect($firstMessage['content'])->toBeArray();
    
    // Should have at least one content part
    expect($firstMessage['content'])->not->toBeEmpty();
});

it('should properly pass messages to StructuredOutput', function () {
    // Create a mock structured output request
    $mockImageData = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
    $prompt = 'Extract data from image';
    $image = new Image($mockImageData, 'image/jpeg', $prompt);
    
    $messages = $image->toMessages();
    
    // Verify that messages is not empty before passing to StructuredOutput
    $messagesArray = $messages->toArray();
    expect($messagesArray)->not->toBeEmpty();
    
    // Test with a simple class
    class TestModel {
        public string $test = 'value';
    }
    
    // Create structured output with messages
    $structuredOutput = new StructuredOutput();
    
    // This should work without throwing a "missing messages" error
    // We can't easily test the actual request without mocking the HTTP client
    // But we can verify the messages are properly structured
    $pendingOutput = $structuredOutput->with(
        messages: $messages,
        responseModel: TestModel::class,
    );
    
    // The fact that no exception was thrown means messages were accepted
    expect($pendingOutput)->toBeInstanceOf(StructuredOutput::class);
});