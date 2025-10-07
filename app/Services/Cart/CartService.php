<?php

namespace App\Services\Cart;

use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;
use App\Http\Dto\Cart\CartPharmaciesDTO;
use App\Http\Dto\Cart\CartProductItemDTO;
use App\Models\EVO\EvoOffers;
use App\Models\PharmaciesView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartsView;
use App\Models\CartProductPharmacyView;
use App\Models\CartsDetailed;
use Mockery\Matcher\Any;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CartService
{
    const MINSK_CENTER_LAT = 53.9;
    const MINSK_CENTER_LONG = 27.5667;

    public function __construct(
        private readonly Cart $Cart,
        protected readonly CartsView $CartsView,
        protected readonly CartsDetailed $CartsDetailed,
        protected readonly CartProductPharmacyView $CartProductPharmacyView,
        protected readonly EvoOffers $evoOffers,
    ) {}

    public function addToCart(CartDTO $cartDTO)
    {
        DB::transaction(function () use ($cartDTO) {
            $pharmacyId = $cartDTO->pharmacy_id;
            $cart = $cartDTO->cart;
            $productId = $cartDTO->product_id;

            $quantityMax = $this->getQuantity($productId, $pharmacyId);

            if ($cartDTO->quantity > $quantityMax) {
                throw new UnprocessableEntityHttpException('Больше нет в наличии');
            }

            $lockedCart = Cart::where('id', $cart->id)->lockForUpdate()->first();

            if ($cartDTO->quantity <= 0) {
                $lockedCart->products()->detach($productId);
            } else {
                $lockedCart->products()->syncWithoutDetaching([
                    $productId => ['quantity' => $cartDTO->quantity]
                ]);
            }

        });
    }

    public function addMultipleToCart(User $user, array $products): void
    {
        if (empty($products)) {
            return;
        }

        $cart = $user->cart;

        $itemsToSync = collect($products)->mapWithKeys(function ($item) {
            return [$item['product_id'] => ['quantity' => $item['quantity']]];
        })->all();

        $cart->products()->syncWithoutDetaching($itemsToSync);
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

    public function getProductPharmacyCart(string $city = null)
    {
        $cityMap = [
            'minsk'       => 'Минск',
            'zhodino'     => 'Жодино',
            'gomel'       => 'Гомель',
            'brest'       => 'Брест',
            'grodno'      => 'Гродно',
            'vitebsk'     => 'Витебск',
            'mogilev'     => 'Могилев',
            'soligorsk'   => 'Солигорск',
            'osipovichi'  => 'Осиповичи',
            'lida'        => 'Лида',
            'molodechno'  => 'Молодечно',
            'baranovichi' => 'Барановичи',
            'mozyr'       => 'Мозырь',
        ];

        $cityName = $cityMap[strtolower($city ?? '')] ?? ($city ?? '');
        $cityNameLower = strtolower($cityName);

        if (!empty($cityNameLower) && !empty($data['cart']) && is_array($data['cart'])) {
            foreach ($data['cart'] as &$cartItem) {
                if (empty($cartItem['pharmacies']) || !is_array($cartItem['pharmacies'])) {
                    continue;
                }

                foreach ($cartItem['pharmacies'] as $i => &$ph) {
                    $addrLower = strtolower($ph['address'] ?? '');
                    $ph['_has_city'] = (str_contains($addrLower, $cityNameLower)) ? 1 : 0;
                    $ph['_idx'] = $i;
                }
                unset($ph);

                usort($cartItem['pharmacies'], function ($a, $b) {
                    if ($a['_has_city'] !== $b['_has_city']) {
                        return $a['_has_city'] ? -1 : 1;
                    }
                    return $a['_idx'] <=> $b['_idx'];
                });

                foreach ($cartItem['pharmacies'] as &$ph) {
                    unset($ph['_has_city'], $ph['_idx']);
                }
                unset($ph);
            }
            unset($cartItem);
        }
    }

    public function getCartDetailed(CartDetailedDTO $cartDTO): array
    {
        $cart = $this->Cart->query()->where('user_id', auth()->user()->id)->first();
        $cart->pharmacy_id = $cartDTO->pharmacyId;
        $cart->promocodes = $cartDTO->promocodes;
        $cart->delivery_zone = $cartDTO->deliveryZone ?? null;
        $cart->save();

        $dataModel = $this->CartsDetailed
            ->query()
            ->where('user_id', auth()->id())
            ->first();

        if (! $dataModel) {
            return [];
        }

        $data = $dataModel->toArray();

        $products = $data['cart']['products'] ?? [];

        $itemsDto = array_map(
            fn(array $p) => new CartProductItemDTO(
                productId: $p['product_id'],
                quantity: $p['quantity'],
            ),
            $products
        );

        $pharmDto = new CartPharmaciesDTO(
            geoLat: ($cart->geo_lat > 0) ? $cart->geo_lat : self::MINSK_CENTER_LAT,
            geoLong: ($cart->geo_long > 0) ? $cart->geo_long : self::MINSK_CENTER_LONG,
            products: $itemsDto,
        );

        $pharmacies = $this->getPriorityPharmacy($pharmDto);

        $selected = collect($pharmacies)
            ->firstWhere('pharmacy_id', $cartDTO->pharmacyId);

        if ($selected) {
            $data['pharmacy_name'] = $selected['pharmacy_name'];
            $data['pharmacy_address'] = $selected['address'];
            $data['pharmacy_availability'] = $selected['availability'];
        } else {
            $data['pharmacy_name'] = null;
            $data['pharmacy_address'] = null;
            $data['pharmacy_availability'] = 'absent';
        }
        $data['cart']['pharmacy']['distance_meters'] = 0; //todo: fast fix
        return $data;
    }

    public function getProductByPharmacies(CartPharmaciesDTO $dto, bool $withoutDelivery = false): array
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
            fn(array $e) => in_array($e['product_id'], $requestedIds, true)
        );

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

                if (
                    $phId === PharmaciesView::PHARMACY_ID_FOR_DELIVERY && $withoutDelivery) {
                    continue;
                }

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
                    ];
                }

                $detail     = $productDetails->get($prodId);
                $stockCount = (float) ($ph['stock_count'] ?? 0);
                $price      = (float) ($ph['price']       ?? 0);
                $priceOld   = (float) ($ph['price_old']   ?? 0);
                $discount   = $priceOld - $price;

                $pharmacies[$phId]['products'][] = [
                    'product_id'         => $prodId,
                    'name'               => $detail->pagetitle ?? '',
                    'image'              => $detail->image ?? null,
                    'requested_quantity' => $reqQty,
                    'stock_count'        => $stockCount,
                    'availability'       => $reqQty <= $stockCount ? 'full' : 'part',
                    'price'              => $price,
                    'price_old'          => $priceOld,
                    'is_recipe'          => filter_var($detail->is_recipe ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_alcohol'         => ($detail->is_alcohol ?? 'no') === 'yes',
                ];

                $pharmacies[$phId]['total_products']++;
                $pharmacies[$phId]['total_price']     += $price    * $reqQty;
                $pharmacies[$phId]['total_price_old'] += $priceOld * $reqQty;
                $pharmacies[$phId]['total_discount']  += $discount * $reqQty;
            }
        }

        foreach ($pharmacies as &$ph) {
            $existing = array_column($ph['products'], 'product_id');
            foreach ($requestedIds as $pid) {
                if (!in_array($pid, $existing, true)) {
                    $detail = $productDetails->get($pid);
                    $ph['products'][] = [
                        'product_id'         => $pid,
                        'name'               => $detail->pagetitle ?? '',
                        'image'              => $detail->image     ?? null,
                        'requested_quantity' => $quantityMap[$pid],
                        'stock_count'        => 0.0,
                        'availability'       => 'absent',
                        'price'              => 0.0,
                        'price_old'          => 0.0,
                        'is_recipe'          => filter_var($detail->is_recipe ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_alcohol'         => ($detail->is_alcohol ?? 'no') === 'yes',
                    ];
                }
            }
        }
        unset($ph);

        foreach ($pharmacies as &$ph) {
            $productAvailabilities = array_column($ph['products'], 'availability');
            $unique = array_values(array_unique($productAvailabilities));

            if (count($unique) === 1 && $unique[0] === 'absent') {
                $ph['availability'] = 'absent';
            } elseif (in_array('part', $productAvailabilities, true)
                || in_array('absent', $productAvailabilities, true)) {
                $ph['availability'] = 'part';
            } else {
                $ph['availability'] = 'full';
            }
        }
        unset($ph);

        $availabilityPriority = [
            'full'   => 0,
            'part'   => 1,
            'absent' => 2,
        ];

        usort($pharmacies, function ($a, $b) use ($availabilityPriority) {
            $pa = $availabilityPriority[$a['availability'] ?? 'absent'] ?? PHP_INT_MAX;
            $pb = $availabilityPriority[$b['availability'] ?? 'absent'] ?? PHP_INT_MAX;

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            $da = isset($a['distance_meters']) ? (float)$a['distance_meters'] : INF;
            $db = isset($b['distance_meters']) ? (float)$b['distance_meters'] : INF;

            return $da <=> $db;
        });

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

    public function getQuantity(int $productId, int $pharmacyId = null): float
    {
        $offers = $this->evoOffers
            ->query()
            ->where('product_id', $productId)
            ->when($pharmacyId, fn ($q) => $q
                ->where(fn ($query) => $query
                    ->where('pharmacy_id', $pharmacyId)
                    ->orWhere('pharmacy_id', PharmaciesView::PHARMACY_ID_FOR_DELIVERY)
                )
            )
            ->get(['stock_count']);
        $offers = $offers->sortBy('stock_count', SORT_NATURAL);

        if (count($offers) > 0) {
            return (float) $offers->last()->stock_count;
        }
        return 0;
    }

    public function getCartDetailedWithoutChoosenPharm(CartDetailedDTO $cartDTO)
    {
        $cart = $this->Cart->query()->where('user_id', auth()->user()->id)->first();
        $cart->promocodes = $cartDTO->promocodes;
        $cart->delivery_zone = $cartDTO->deliveryZone ?? null;
        $cart->save();

        $data =$this->getCart();

        if (!$data) {
             return [];
        }

        $data = $this->formatCartData($data);

        $data['pharmacy_name'] = 'Аптека не выбрана';
        $data['pharmacy_address'] = 'Аптека не выбрана';
        $data['pharmacy_availability'] = 'full';

        return $data;
    }

    private function formatCartData (CartsView $cartsView):array {
        $cartsView = $cartsView->toArray();

        $products = $cartsView['product_info'];

        $finalArray = [
            'user_id' => $cartsView['user_id'],
            'cart' => [
                'totals' => [
                    "delivery_sum" => null,
                    "total_discount" => 0,
                    "total_price_old" => 0,
                    "total_final_price" => 0,
                    "discount_only_promos" => 0,
                ],
                'cart_id'  => $cartsView['cart']['cart_id'],
                'user_id'  => $cartsView['user_id'],
                'products' => [],
                'all_promocodes' => [],
                'entered_promocodes' => '',
            ],
            'distance' => null,
            'availability_priority' => 0
        ];
        $totalSum = 0;
        if (is_array($products)) {
            foreach ($products as $product ) {
                $sumProduct = round($product['quantity']*$product['product_charachters']['product_price_from'],2);
                $totalSum = $totalSum+$sumProduct;
                $finalArray['cart']['products'][] = [
                    'prices' => [
                        'price' => round($product['product_charachters']['product_price_from'], 2),
                        'final_price' => round($product['product_charachters']['product_price_from'], 2),
                        'price_old' => round($product['product_charachters']['product_price_from'], 2),
                    ],
                    'quantity' => $product['quantity'],
                    'product_id' => $product['product_charachters']['product_id'],
                    'availability' => 'full',
                    'action_json' => $product['action_json'],
                    'product_info' => $product['product_charachters'],
                    'product_totals' =>  [
                        "total" => $sumProduct,
                        "total_old" => $sumProduct,
                        "final_total" => $sumProduct,
                        "final_total_with_promos" => $sumProduct,
                        "total_discount_only_promos" => 0.0,
                        "total_discount_with_promos" => 0.0,
                        "total_discount_without_promos" => 0.0,
                    ],
                ];
            }
        }

        $finalArray['cart']['totals']['total_price_old'] = round($totalSum, 2);
        $finalArray['cart']['totals']['total_final_price'] = round($totalSum, 2);

        return $finalArray;
}
    public function getPriorityPharmacy(CartPharmaciesDTO $dto)
    {
        $need = implode(', ', array_fill(0, count($dto->products), '?'));
        $userId = auth()->user()->id;
        $requestedIds = array_map(fn($item) => $item->productId, $dto->products);

        $result = DB::select('SELECT
	            carts.user_id,
	            carts.id as cart_id,
	            cart_evo_site_content.evo_site_content_id AS product_id,
                        cart_evo_site_content.quantity AS required_quantity,
                        evo_product_pharmacy_view.pharmacy_id,
                        evo_product_pharmacy_view.pharmacy_name,
                        evo_product_pharmacy_view.coordinates,
                        evo_product_pharmacy_view.schedule,
                        evo_product_pharmacy_view.address,
                        evo_product_pharmacy_view.stock_count,
                        evo_product_pharmacy_view.price,
                        evo_product_pharmacy_view.price_old,
	            IF(evo_product_pharmacy_view.stock_count >= cart_evo_site_content.quantity, \'full\', \'part\') AS availability,
	            IF(evo_product_pharmacy_view.stock_count >= cart_evo_site_content.quantity, 0, IF(evo_product_pharmacy_view.stock_count > 0, 1, 2)) AS pharmacy_rang,
                    CASE WHEN carts.geo_lat = \'\'  OR carts.geo_long = \'\' THEN 0
                 ELSE ROUND( ST_Distance_Sphere (
	            				POINT ( carts.geo_long, carts.geo_lat ),
	            				POINT ( CAST( SUBSTRING_INDEX( evo_product_pharmacy_view.coordinates, \',\', - 1 ) AS DECIMAL ( 10, 6 )), CAST( SUBSTRING_INDEX( evo_product_pharmacy_view.coordinates, \',\', 1 ) AS DECIMAL ( 10, 6 )))  )
	            		)
	            	END AS distance_meters
	            FROM
	            	carts
	            	LEFT JOIN evo_product_pharmacy_view ON evo_product_pharmacy_view.pharmacy_id <> '.PharmaciesView::PHARMACY_ID_FOR_DELIVERY.'
	            	JOIN cart_evo_site_content  ON evo_product_pharmacy_view.product_id = cart_evo_site_content.evo_site_content_id
	            WHERE
	            	carts.user_id = ?
	            AND evo_product_pharmacy_view.product_id IN ('.$need.')
	            and evo_product_pharmacy_view.stock_count > 0
	            ORDER BY distance_meters, pharmacy_id', [$userId, ...$requestedIds]);

        if (!$result) {
            return [];
        }
        $quantityMap = [];
        foreach ($dto->products as $item) {
            $quantityMap[$item->productId] = $item->quantity;
        }

        $pharmacies = [];
        $products = [];
        foreach ($result as $item) {
            $products[$item->pharmacy_id][$item->product_id] = (array) $item;
            $pharmacies[$item->pharmacy_id] = [
                'pharmacy_name' => $item->pharmacy_name,
                'coordinates' => $item->coordinates,
                'stock_count' => $item->stock_count,
                'required_quantity' => $item->required_quantity,
                'pharmacy_id' => $item->pharmacy_id,
                'address' => $item->address,
                'products' => $products[$item->pharmacy_id],
                'distance_meter' => 0
            ];
        }

        foreach ($pharmacies as $key => &$pharmacy) {
            $existing = array_column($pharmacies[$key]['products'], 'product_id');
            foreach ($requestedIds as $id) {
                if (!in_array($id, $existing, true)) {
                    $pharmacy['products'][] = [
                        'product_id' => $id,
                        'requested_quantity' => $quantityMap[$id],
                        'stock_count' => 0.0,
                        'availability' => 'absent',
                        "pharmacy_id" => $key,
                        'distance_meter2' => 0
                    ];
                }
            }
        }
        unset($pharmacy);
        foreach ($pharmacies as &$ph) {
            $productAvailabilities = array_column($ph['products'], 'availability');
            $unique = array_values(array_unique($productAvailabilities));

            if (count($unique) === 1 && $unique[0] === 'absent') {
                $ph['availability'] = 'absent';
            } elseif (in_array('part', $productAvailabilities, true)
                || in_array('absent', $productAvailabilities, true)) {
                $ph['availability'] = 'part';
            } else {
                $ph['availability'] = 'full';
            }
        }

        $availabilityPriority = [
            'full'   => 0,
            'part'   => 1,
            'absent' => 2,
        ];

        usort($pharmacies, function ($a, $b) use ($availabilityPriority) {
            $pa = $availabilityPriority[$a['availability'] ?? 'absent'] ?? PHP_INT_MAX;
            $pb = $availabilityPriority[$b['availability'] ?? 'absent'] ?? PHP_INT_MAX;

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            $da = isset($a['distance_meters']) ? (float)$a['distance_meters'] : INF;
            $db = isset($b['distance_meters']) ? (float)$b['distance_meters'] : INF;

            return $da <=> $db;
        });

        return array_values($pharmacies);


    }
}
