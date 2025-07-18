---
title: 'Image processing - car damage detection'
docname: 'image_car_damage'
---

## Overview

This is an example of how to extract structured data from an image using
Instructor. The image is loaded from a file and converted to base64 format
before sending it to OpenAI API.

In this example we will be extracting structured data from an image of a car
with visible damage. The response model will contain information about the
location of the damage and the type of damage.

## Scanned image

Here's the image we're going to extract data from.

![Car Photo](/images/car-damage.jpg)


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Image\Image;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Utils\Str;

enum DamageSeverity : string {
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case Total = 'total';
}

enum DamageLocation : string {
    case Front = 'front';
    case Rear = 'rear';
    case Left = 'left';
    case Right = 'right';
    case Top = 'top';
    case Bottom = 'bottom';
}

class Damage {
    #[Description('Identify damaged element')]
    public string $element;
    /** @var DamageLocation[] */
    public array $locations;
    public DamageSeverity $severity;
    public string $description;
}

class DamageAssessment {
    public string $make;
    public string $model;
    public string $bodyColor;
    /** @var Damage[] */
    public array $damages = [];
    public string $summary;
}

$assessment = Image::fromFile(__DIR__ . '/car-damage.jpg')
    ->toData(
        responseModel: DamageAssessment::class,
        prompt: 'Identify and assess each car damage location and severity separately.',
        connection: 'openai',
        model: 'gpt-4o',
        options: ['max_tokens' => 4096]
    );

dump($assessment);
assert(Str::contains($assessment->make, 'Toyota', false));
assert(Str::contains($assessment->model, 'Prius', false));
assert(Str::contains($assessment->bodyColor, 'white', false));
assert(count($assessment->damages) > 0);
?>
```
