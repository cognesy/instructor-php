<?php

namespace Cognesy\Messages\Tests\Fixtures;

use Cognesy\Messages\Utils\Image;

class MockImage extends Image {
    public function toArray(): array {
        return ['type' => 'image', 'url' => 'http://example.com/image.jpg'];
    }
}
