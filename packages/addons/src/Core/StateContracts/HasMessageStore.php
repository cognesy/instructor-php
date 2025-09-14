<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StateContracts;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;

interface HasMessageStore
{
    public function messages(): Messages;
    public function store(): MessageStore;
    public function withStore(MessageStore $store): static;
}