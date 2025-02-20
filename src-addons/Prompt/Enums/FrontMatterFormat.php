<?php

namespace Cognesy\Addons\Prompt\Enums;

enum FrontMatterFormat : string
{
    case Yaml = 'yaml';
    case Json = 'json';
    case Toml = 'toml';
    case None = 'none';
}
