<?php

namespace App\Http\Dto\Product;

final class ProductDTO
{
    public function __construct(
        public string $productId='',
        public int $priceFrom=0,
        public int $priceTo=100000000,
        public array $releaseForm=[],
        public array $form=[],
        public array $brand=[],
        public array $country=[],
        public ?bool $recipe=null,
        public bool $action=false,
        public ?bool $delivery=false,
        public ?bool $available=false,
        public ?string $sortBy='popularity',
        public ?int $categoryId=2,
        public ?int $pharmacyId=0,
        public ?string $pharmacyAddress='',
        public ?string $city = null,
        public ?array $pharmacyDelivery=['Доставка', 'Самовывоз'],
    ) {}
}
