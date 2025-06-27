<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Cart\CartService;
use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $CartService,
    ) {
        if (!auth()->user()->cart) {
            $this->CartService->checkCart();
            auth()->user()->refresh();
        }
    }

    public function getCart(Request $request): JsonResponse
    {
        return $this->responseOk($this->CartService->getCart());
    }

    public function getCartDetailed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pharmacy_id' => ['required', 'integer'],
            'promocodes' => ['nullable', 'string'],
            'delivery_zone' => ['nullable', 'string'],
            'geo_lat' => ['nullable', 'string'],
            'geo_long' => ['nullable', 'string'],
        ]);

        $cartDTO = new CartDetailedDTO(
            pharmacyId: $validated['pharmacy_id'],
            deliveryZone: $validated['delivery_zone'] ?? null,
            promocodes: $validated['promocodes'] ?? ''
        );

        $this->CartService->setUserGeo($validated['geo_lat'] ?? '', $validated['geo_long'] ?? '');

        return $this->responseOk($this->CartService->getCartDetailed($cartDTO));
    }

    public function getProductPharmacyCart(Request $request): JsonResponse
    {
        $this->CartService->setUserGeo($request->get('geo_lat', ''), $request->get('geo_long', ''));
        return $this->responseOk($this->CartService->getProductPharmacyCart());
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer'],
        ]);

        $cartDTO = new CartDTO(
            user:   $request->user(),
            cart:   $request->user()->cart,
            product_id: $validated['product_id'],
            quantity:   $validated['quantity']
        );

        $this->CartService->addToCart($cartDTO);
        return $this->responseOk();
        //return $this->getCart($request);
    }

    public function clearCart(Request $request): JsonResponse
    {
        $this->CartService->clearCart($request->user());
        return $this->responseOk();
        //return $this->getCart($request);
    }

    public function addPromocode(Request $request): JsonResponse
    {

        return $this->responseOk();
    }

    public function deletePromocode(Request $request): JsonResponse
    {
        return $this->responseOk();
    }
}
