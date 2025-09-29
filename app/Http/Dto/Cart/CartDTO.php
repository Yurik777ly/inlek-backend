<?php

namespace App\Http\Dto\Cart;

use App\Http\Dto\BaseDTO;
use App\Models\Cart;
use App\Models\User;

class CartDTO extends BaseDTO
{
    public function __construct(
        public User $user,
        public ?Cart $cart = null,
        public ?int $product_id = null,
        public ?int $quantity = null,
        public ?int $pharmacy_id = null,
    )
    {}
}
