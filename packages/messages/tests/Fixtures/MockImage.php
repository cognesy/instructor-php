<?php

namespace Cognesy\Messages\Tests\Fixtures;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Utils\Image;

class MockImage extends Image {
    public function toContentPart(): ContentPart {
        return new ContentPart('image_url', [
            'image_url' => ['url' => 'http://example.com/image.jpg']
        ]);
    }
}
