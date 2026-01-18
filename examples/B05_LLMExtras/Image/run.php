<?php
require 'examples/boot.php';

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Utils\Image;
use Cognesy\Polyglot\Inference\Inference;

$messages = Messages::empty()
    ->asSystem('You are an expert in car damage assessment.')
    ->asUser(Content::empty()
        ->addContentPart(ContentPart::text('Describe the car damage in the image.'))
        ->addContentPart(Image::fromFile(__DIR__ . '/car-damage.jpg')->toContentPart())
    );

$response = (new Inference)
    ->using('openai')
    ->withModel('gpt-4o-mini')
    ->withMessages($messages)
    ->get();

echo "Response: " . $response . "\n";
