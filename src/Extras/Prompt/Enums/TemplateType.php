<?php

namespace Cognesy\Instructor\Extras\Prompt\Enums;

enum TemplateType : string
{
    case Twig = 'twig';
    case Blade = 'blade';
    case Unknown = 'unknown';
}
