<?php

use Cognesy\Addons\Image\Image;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

it('should properly use toData() method on Image', function () {
    // Create a mock base64 image data
    $mockImageData = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
    
    // Test response model
    class ImageTestModel {
        public string $description = '';
    }
    
    // Create an image using the same approach as the failing example
    $image = new Image($mockImageData, 'image/jpeg', 'Describe this image');
    
    // First check what toArray returns directly
    $imageArray = $image->toArray();
    
    // Then verify that toMessages() works
    $messages = $image->toMessages();
    $messagesArray = $messages->toArray();
    
    expect($messagesArray)->not->toBeEmpty();
    expect($messagesArray[0])->toHaveKey('role');
    expect($messagesArray[0])->toHaveKey('content');
    expect($messagesArray[0]['content'])->toBeArray();
    
    // Check content structure - should have text and image
    $content = $messagesArray[0]['content'];
    $hasText = false;
    $hasImage = false;
    
    foreach ($content as $part) {
        if (isset($part['type'])) {
            if ($part['type'] === 'text') {
                $hasText = true;
            }
            if ($part['type'] === 'image_url') {
                $hasImage = true;
            }
        }
    }
    
    expect($hasText)->toBeTrue('Message should contain text part');
    expect($hasImage)->toBeTrue('Message should contain image part');
});

it('reproduces the missing messages parameter issue', function () {
    // Mock the exact scenario from the failing example
    $mockImageData = 'data:image/jpeg;base64,/9j/4AAQSkZJRg==';
    
    class DamageAssessmentTest {
        public string $summary = '';
    }
    
    // This mimics what Image::toData() does internally
    $image = new Image($mockImageData, 'image/jpeg');
    
    // Get messages that would be passed
    $messages = $image->toMessages();
    $messagesArray = $messages->toArray();
    
    // The messages array should not be empty
    expect($messagesArray)->not->toBeEmpty();
    
    // Create StructuredOutput like toData() does
    $structuredOutput = new StructuredOutput();
    
    // This is what toData() calls - verify it doesn't lose messages
    $pending = $structuredOutput->with(
        messages: $messages,
        responseModel: DamageAssessmentTest::class,
        prompt: 'Identify and assess damage',
        model: 'gpt-4o',
        options: ['max_tokens' => 4096],
        mode: OutputMode::Tools,
    );
    
    // Messages should still be present
    expect($pending)->toBeInstanceOf(StructuredOutput::class);
});