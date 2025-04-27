<?php

namespace Cognesy\Utils\Tests\Fixtures;

use Cognesy\Utils\Messages\Utils\Image;

class MockImage extends Image {
    public function toArray(): array {
        return ['type' => 'image', 'url' => 'http://example.com/image.jpg'];
    }
}
