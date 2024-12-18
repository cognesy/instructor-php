<?php

namespace Cognesy\Instructor\Extras\Prompt\Enums;

enum TemplateEngineType : string
{
    case Twig = 'twig';
    case Blade = 'blade';
    case Arrowpipe = 'arrowpipe';
}
