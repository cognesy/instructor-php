<?php

namespace Cognesy\Utils\Tests\Fixtures;

use Cognesy\Utils\Image\Image;

class MockImage extends Image {
    public function toArray(): array {
        return ['type' => 'image', 'url' => 'http://example.com/image.jpg'];
    }
}
