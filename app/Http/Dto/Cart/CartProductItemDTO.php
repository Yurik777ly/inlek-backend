<?php

namespace App\Http\Dto\Cart;

class CartProductItemDTO
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}
