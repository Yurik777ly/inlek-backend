<?php

namespace App\Marketing\DTO;

class CartItem
{
    /**
     * ID товара сайта (evo_site_content.id)
     *
     * @var int
     */
    public $productId;

    /**
     * Базовая цена из Offers 1С (price_old).
     *
     * @var float
     */
    public $priceOld;

    /**
     * Цена предложения из Offers 1С (price_new).
     *
     * @var float
     */
    public $priceNew;

    /**
     * Количество.
     *
     * @var int
     */
    public $quantity;

    /**
     * Стоимость позиции по price_new.
     *
     * @var float
     */
    public $sum;

    /**
     * ID аптеки сайта (site_content.id).
     *
     * @var int
     */
    public $pharmacyId;

    /**
     * ID_punkt аптеки в 1С.
     *
     * @var string
     */
    public $pharmacyUid;

    /**
     * parent_id группы аптеки в 1С.
     *
     * @var string
     */
    public $pharmacyParentId;

    public function __construct(
        int $productId,
        float $priceOld,
        float $priceNew,
        int $quantity,
        float $sum = 0.0,
        int $pharmacyId = 0,
        string $pharmacyUid = '',
        string $pharmacyParentId = ''
    ) {
        $this->productId = $productId;
        $this->priceOld = $priceOld;
        $this->priceNew = $priceNew;
        $this->quantity = $quantity;
        $this->sum = $sum;
        $this->pharmacyId = $pharmacyId;
        $this->pharmacyUid = $pharmacyUid;
        $this->pharmacyParentId = $pharmacyParentId;

        if ($this->sum <= 0.0) {
            $this->sum = $this->priceNew * $this->quantity;
        }
    }
}
