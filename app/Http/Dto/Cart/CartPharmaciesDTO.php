<?php

namespace App\Http\Dto\Cart;

class CartPharmaciesDTO
{
    /**
     * @param float $geoLat
     * @param float $geoLong
     * @param CartProductItemDTO[] $products
     */
    public function __construct(
        public float $geoLat,
        public float $geoLong,
        public array $products,
    ) {}
}
