<?php

namespace App\Http\Dto\Auth;

final class AuthDTO
{
    public function __construct(
        public string $phone,
        public ?string $code = null,
        public ?string $password = null,
        public ?string $fcm_token = null,
    ) {}
}
