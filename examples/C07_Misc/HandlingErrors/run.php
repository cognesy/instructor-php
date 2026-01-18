<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class UserDetail
{
    public string $name;
    public int $age;
}

class MaybeUser
{
    public ?UserDetail $user = null;
    public bool $noUserData = false;
    /** If no user data, provide reason */
    public ?string $errorMessage = '';

    public function get(): ?UserDetail {
        return $this->noUserData ? null : $this->user;
    }
}

$user = (new StructuredOutput)
    ->withMessages([['role' => 'user', 'content' => 'We don\'t know anything about this guy.']])
    ->withResponseModel(MaybeUser::class)
    ->get();

dump($user);

assert($user->noUserData);
assert(!empty($user->errorMessage));
assert($user->get() === null);
?>
