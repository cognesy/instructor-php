<?php
namespace Cognesy\Auxiliary\Web\Contracts;

interface CanProcessHtml
{
    public function getMetadata(string $html, array $attributes = []): array;
    public function getTitle(string $html) : string;
    public function getBody(string $html) : string;
}
