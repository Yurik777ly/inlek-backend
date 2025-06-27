<?php

namespace App\Http\Dto\Profile;

use App\Http\Dto\BaseDTO;

class ProfileDTO extends BaseDTO
{
    public function __construct(
        public string $userId,
        public string $phone,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $gender = null,
        public ?string $birthday = null,
        public ?string $email = null,
        public ?string $oldPassword = null,
        public ?string $newPassword = null,
        public ?string $newPasswordConfirm = null,
        public ?bool   $statusNotifications = null,
        public ?bool   $acceptPolicy = null,
        public ?string $code = null
    )
    {
        $this->hidden = ['oldPassword', 'newPassword', 'newPasswordConfirm', 'code'];
    }
}
