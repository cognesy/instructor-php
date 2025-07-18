<?php declare(strict_types=1);

namespace Cognesy\Messages\Enums;

enum ContentType: string
{
    case Text = 'text';
    case Image = 'image_url';
    case File = 'file';
}