<?php

namespace App\Services\Cart;

use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;
use App\Http\Dto\Cart\CartPharmaciesDTO;
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

    public function getProductByPharmacies(CartPharmaciesDTO $dto)
    {
        $userId = auth()->user()->id;
        $record = CartProductPharmacyView::query()
            ->where('user_id', $userId)
            ->first(['cart']);

        if (!$record) {
            return [];
        }

        $requestedIds = array_map(fn($item) => $item->productId, $dto->products);
        $quantityMap = [];

        foreach ($dto->products as $item) {
            $quantityMap[$item->productId] = $item->quantity;
        }

        $cartEntries = array_filter(
            $record->cart,
            fn(array $entry) => in_array($entry['product_id'], $requestedIds)
        );

        if (empty($cartEntries)) {
            return [];
        }

        foreach ($cartEntries as &$entry) {
            foreach ($entry['pharmacies'] as &$ph) {
                if (!empty($dto->geoLat) && !empty($dto->geoLong) && !empty($ph['coordinates'])) {
                    [$lat, $lng] = explode(',', $ph['coordinates']);
                    $ph['distance_meters'] = $this->haversineDistance(
                        $dto->geoLat,
                        $dto->geoLong,
                        (float) trim($lat),
                        (float) trim($lng)
                    );
                }
            }
        }
        unset($entry, $ph);

        $productDetails = DB::table('evo_products_view')
            ->select('product_id', 'pagetitle', 'image', 'is_recipe', 'is_alcohol')
            ->whereIn('product_id', $requestedIds)
            ->get()
            ->keyBy('product_id');

        $pharmacies = [];

        foreach ($cartEntries as $entry) {
            $prodId = $entry['product_id'];
            $reqQty = $quantityMap[$prodId] ?? 0;

            foreach ($entry['pharmacies'] as $ph) {
                $phId = $ph['pharmacy_id'];

                if (!isset($pharmacies[$phId])) {
                    $pharmacies[$phId] = [
                        'pharmacy_id'      => $phId,
                        'pharmacy_name'    => $ph['pharmacy_name'] ?? null,
                        'address'          => $ph['address'] ?? null,
                        'coordinates'      => $ph['coordinates'] ?? null,
                        'schedule'         => $ph['schedule'] ?? null,
                        'distance_meters'  => $ph['distance_meters'] ?? null,
                        'products'         => [],
                        'total_products'   => 0,
                        'total_price'      => 0.0,
                        'total_price_old'  => 0.0,
                        'total_discount'   => 0.0,
                        'is_out_of_stock'  => true,
                    ];
                }

                $detail = $productDetails->get($prodId);
                $name = $detail->pagetitle ?? '';
                $image = $detail->image ?? null;
                $isRecipe = isset($detail->is_recipe) ? filter_var($detail->is_recipe, FILTER_VALIDATE_BOOLEAN) : false;
                $isAlcohol = isset($detail->is_alcohol) && $detail->is_alcohol === 'yes';

                $stockCount = isset($ph['stock_count']) ? (float) $ph['stock_count'] : 0.0;
                $price = isset($ph['price']) ? (float) $ph['price'] : 0.0;
                $priceOld = isset($ph['price_old']) ? (float) $ph['price_old'] : 0.0;
                $discount = $priceOld - $price;

                $pharmacies[$phId]['products'][] = [
                    'product_id'         => $prodId,
                    'name'               => $name,
                    'image'              => $image,
                    'requested_quantity' => $reqQty,
                    'stock_count'        => $stockCount,
                    'availability'       => $reqQty <= $stockCount ? 'full' : 'part',
                    'price'              => $price,
                    'price_old'          => $priceOld,
                    'is_recipe'          => $isRecipe,
                    'is_alcohol'         => $isAlcohol,
                ];

                $pharmacies[$phId]['total_products']++;
                $pharmacies[$phId]['total_price']      += $price * $reqQty;
                $pharmacies[$phId]['total_price_old']  += $priceOld * $reqQty;
                $pharmacies[$phId]['total_discount']   += $discount * $reqQty;
            }
        }

        foreach ($pharmacies as &$ph) {
            $productAvailabilities = array_column($ph['products'], 'availability');
            $ph['availability'] = !in_array('part', $productAvailabilities, true) ? 'full' : 'part';
        }
        unset($ph);

        usort($pharmacies, fn($a, $b) =>
            ($a['distance_meters']  ?? INF)
            <=>
            ($b['distance_meters']  ?? INF)
        );

        return array_values($pharmacies);
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
