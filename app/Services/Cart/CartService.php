<?php

namespace App\Services\Cart;

use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartsView;
use App\Models\CartProductPharmacyView;
use App\Models\CartsDetailed;
use Mockery\Matcher\Any;

class CartService
{
    public function __construct(
        private readonly Cart $Cart,
        protected readonly CartsView $CartsView,
        protected readonly CartsDetailed $CartsDetailed,
        protected readonly CartProductPharmacyView $CartProductPharmacyView,
    ) {}

    public function addToCart(CartDTO $cartDTO)
    {
        $quantityArr = ['quantity' => $cartDTO->quantity];
        $contains = $cartDTO->cart->products->contains($cartDTO->product_id);

        if ($contains) {
            if($cartDTO->quantity <= 0) {
                $cartDTO->cart->products()->detach(
                    $cartDTO->product_id
                );
            } else {
                $cartDTO->cart->products()->updateExistingPivot(
                    $cartDTO->product_id,
                    $quantityArr
                );
            }
        } else {
            if($cartDTO->quantity >0) {
                $cartDTO->cart->products()->attach(
                    $cartDTO->product_id,
                    $quantityArr
                );
            }
        }
    }
    public function clearCart(User $user)
    {
        $user->cart->products()->detach();
    }

    public function checkCart()
    {
        if (!auth()->user()->cart) {
            $this->Cart->create([
                'user_id' => auth()->user()->id,
                'promo' => '1'
            ]);
        }
    }

    public function getCart()
    {
        $this->checkCart();
        $data = $this->CartsView->query()->where('user_id', auth()->user()->id)->first();
        return ($data) ? $data : [];
    }

    public function setUserGeo(String $lat, String $long) {
        $cart = $this->Cart->query()->where('user_id', auth()->user()->id)->first();
        $cart->geo_lat = $lat;
        $cart->geo_long = $long;
        $cart->save();
    }

    public function getProductPharmacyCart()
    {
        $this->checkCart();
        $data = $this->CartProductPharmacyView->query()->where('user_id', auth()->user()->id)->first();
        if($data) {
            $data = $data->toArray();
        }
        return ($data) ? $data : [];
    }

    public function getCartDetailed(CartDetailedDTO $cartDTO)
    {
        $cart = $this->Cart->query()->where('user_id', auth()->user()->id)->first();
        $cart->pharmacy_id = $cartDTO->pharmacyId;
        $cart->promocodes = $cartDTO->promocodes;
        $cart->delivery_zone = $cartDTO->deliveryZone ?? null;
        $cart->save();

        $data = $this->CartsDetailed->query()->where('user_id', auth()->user()->id)->first();
        if ($data) {
            $data = $data->toArray();
        }
        return ($data) ? $data : [];
    }
}
