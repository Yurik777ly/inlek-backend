<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Dto\Cart\CartPharmaciesDTO;
use App\Http\Dto\Cart\CartProductItemDTO;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Cart\CartService;
use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $CartService,
        protected readonly OrderService $OrderService,
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
        return $this->responseOk($this->CartService->getProductPharmacyCart($request->get('city', null)));
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

    public function getProductPharmacyCartV2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geo_lat'                 => ['required', 'numeric'],
            'geo_long'                => ['required', 'numeric'],
            'products'                => ['required', 'array'],
            'products.*.product_id'   => ['required', 'integer'],
            'products.*.quantity'     => ['required', 'integer'],
        ]);

        $this->CartService->setUserGeo(
            $validated['geo_lat'],
            $validated['geo_long']
        );

        $itemsDto = array_map(
            fn(array $item) => new CartProductItemDTO(
                productId: $item['product_id'],
                quantity: $item['quantity']
            ),
            $validated['products']
        );

        $dto = new CartPharmaciesDTO(
            geoLat:    (float) $validated['geo_lat'],
            geoLong:   (float) $validated['geo_long'],
            products:  $itemsDto,
        );

        return $this->responseOk(
            $this->CartService->getProductByPharmacies($dto)
        );
    }

    public function repeatOrder(Request $request, int $orderId): JsonResponse
    {
        $userId = $request->user()->id;

        $orders = $this->OrderService->getDetailed($userId, $orderId);

        if ($orders->isEmpty()) {
            return $this->responseError('Заказ не найден', 404);
        }

        $order = $orders->first();

        // Проверяем наличие продуктов в заказе
        if (empty($order->order_products_json)) {
            return $this->responseError('В заказе нет товаров для повтора', 400);
        }

        $addedProducts = [];
        $failedProducts = [];

        foreach ($order->order_products_json as $item) {
            try {
                $cartDTO = new CartDTO(
                    user: $request->user(),
                    cart: $request->user()->cart,
                    product_id: $item['product_id'],
                    quantity: $item['count']
                );

                $this->CartService->addToCart($cartDTO);
                $addedProducts[] = [
                    'product_id' => $item['product_id'],
                    'title' => $item['title'] ?? 'Товар',
                    'quantity' => $item['count']
                ];
            } catch (\Exception $e) {
                $failedProducts[] = [
                    'product_id' => $item['product_id'],
                    'title' => $item['title'] ?? 'Товар',
                    'quantity' => $item['count'],
                    'error' => 'Не удалось добавить товар в корзину'
                ];
            }
        }

        return $this->responseOk([
            'message' => 'Заказ повторен',
            'order_id' => $orderId,
            'added_products' => $addedProducts,
            'failed_products' => $failedProducts,
            'added_count' => count($addedProducts),
            'failed_count' => count($failedProducts)
        ]);
    }
}
