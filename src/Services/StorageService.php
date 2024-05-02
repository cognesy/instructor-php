<?php

namespace Cognesy\Instructor\Services;

// TODO: this is part of refactoring in progress - currently not used

class StorageService
{
    public function storeText(string $objectId, string $data) : void {
    }

    public function storeArray(string $objectId, array $data) : void {
    }

    public function storeObject(string $objectId, object $data) : void {
    }

    public function loadText(string $objectId) : string {
        return '';
    }

    public function loadArray(string $objectId) : array {
        return [];
    }

    public function loadObject(string $objectId) : object {
        return new \stdClass;
    }
}