<?php

namespace App\Http\Dto\Cart;

use App\Http\Dto\BaseDTO;
use App\Models\Cart;
use App\Models\User;

class CartDetailedDTO extends BaseDTO
{
    public function __construct(
        public ?int $pharmacyId,
        public ?string $deliveryZone,
        public ?string $promocodes
    )
    {}
}
