<?php

namespace App\Services\Order;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\OrderView;
use App\Models\OrderViewJson;
use App\Models\EVO\EvoCommerceOrderStatuses;
use App\Models\EVO\EvoCommerceOrders;
use App\Models\EVO\EvoCommerceOrderProducts;
use App\Models\EVO\EvoCommerceOrderHistory;
use App\Models\EVO\EvoCommerceOrderPayments;

use App\Services\Payment\Bepaid;
use App\Services\Payment\Oplati;
use App\Services\Payment\EripExpresspay;
use App\Services\Pharmacy\PharmacyService;

use App\Services\Cart\CartService;
use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartDetailedDTO;
use App\Models\ProductInfoViewJson;

const INACTIVE_STATUSES = [
    'Отменен',
    'Получен',
];

const DELIVERY_TITLES = [
    'Доставка курьером',
    'Доставка по Минску',
    'Доставка по Минскому району',
    'Доставка по Минску (зеленая зона)',
    'По Минскому району (красная зона)',
    'Зелёная зона доставки',
    'Жёлтая зона доставки',
];

const SELF_GET_TITLES = [
    'Самовывоз'
];

class OrderService
{
    private array $productCache = [];

    const DEFAULT_PHARMACY = 6864;

    public function __construct(
        protected readonly User $User,
        protected readonly CartService $CartService,
        protected readonly OrderViewJson $OrderViewJson,
        protected readonly EvoCommerceOrderStatuses $EvoCommerceOrderStatuses,
        protected readonly EvoCommerceOrders $EvoCommerceOrders,
        protected readonly EvoCommerceOrderProducts $EvoCommerceOrderProducts,
        protected readonly EvoCommerceOrderHistory $EvoCommerceOrderHistory,
        protected readonly EvoCommerceOrderPayments $EvoCommerceOrderPayments,
        protected readonly PharmacyService $PharmacyService,
        protected readonly ProductInfoViewJson $ProductInfoViewJson,
    ) {}

    public function generateUniqueHash(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s%s%s%s%s%s%s', str_split(bin2hex($data), 4));
    }

    public function pay($payhash): bool
    {
        try {
            $payment = $this->EvoCommerceOrderPayments->query()->where('hash', $payhash)->first();
            if ($payment) {
                $payment->paid = 1;
                $payment->save();
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Payment processing failed', ['hash' => $payhash, 'error' => $e->getMessage()]);
        }
        return false;
    }

    public function create($orderArray)
    {
            return DB::transaction(function () use ($orderArray) {
                // Обновляем информацию о пользователе
                $this->updateUserInfo($orderArray);

                // Получаем данные корзины
                $cartData = $this->getCartData($orderArray);
                if (empty($cartData['products'])) {
                    return false;
                }

                // Рассчитываем суммы заказа
                $orderCalculations = $this->calculateOrderTotals($cartData, $orderArray);

                // Создаем заказ
                $orderId = $this->createOrder($orderArray, $orderCalculations);

                // Сохраняем товары заказа
                $this->saveOrderProducts($orderId, $cartData['orderProducts'], $orderArray['pharmacy_id']);

                // Очищаем корзину
                $this->clearCartProducts(array_column($cartData['orderProducts'], 'product_id'));

                // Создаем запись в истории
                $this->createOrderHistory($orderId);

                // Обрабатываем платеж
                $processor = $this->processOrderPayment($orderId, $orderCalculations['totalSum'], $orderArray['payment']);

                // Обновляем статус заказа
                $this->updateOrderStatus($orderId, 2);

                // Возвращаем ответ
                return $this->formatOrderResponse($orderId, $orderArray, $orderCalculations, $processor, $cartData['orderProducts']);
            });
    }

    private function updateUserInfo(array $orderArray): void
    {
        $user = auth()->user();

        $fields = ['phone', 'first_name', 'last_name', 'email'];

        foreach ($fields as $field) {
            if (empty($user->{$field})) {
                $user->{$field} = $orderArray[$field] ?? null;
            }
        }

        $user->save();
    }

    private function getCartData(array $orderArray): array
    {
        $orderArray['pharmacy_id'] = ($orderArray['pharmacy_id'] == 0) ? self::DEFAULT_PHARMACY : $orderArray['pharmacy_id'];

        $cartDTO = new CartDetailedDTO(
            pharmacyId: $orderArray['pharmacy_id'],
            deliveryZone: $orderArray['delivery_zone'] ?? null,
            promocodes: implode(',', $orderArray['promocodes'] ?? [])
        );

        $this->CartService->setUserGeo('', '');
        $products = $this->CartService->getCartDetailed($cartDTO);

        if (empty($products)) {
            return [];
        }

        $productIds = array_intersect(
            array_column($products['cart']['products'], 'product_id'),
            $orderArray['ids']
        );

        $productsFullInfo = $this->getProductsInfo($productIds);

        $position = 1;
        
        $sum = 0;
        $oldsum = 0;
        $orderProducts = [];

        foreach ($products['cart']['products'] as $product) {
            if (in_array($product['product_id'], $orderArray['ids'])) {
                $price = (float)$product['prices']['final_price'];
                $price_old = (float)$product['prices']['price_old'] ?? 0;
                $quantity = $product['quantity'];
                $sum = $sum + $price * $quantity;
                $oldsum = $oldsum + $price_old * $quantity;

                $fullProductInfo = $productsFullInfo->get($product['product_id']);
                $productCharachters = null;
                $productPromocodes = null;

                if ($fullProductInfo) {
                    $productCharachters = $this->safeJsonDecode($fullProductInfo->product_charachters);
                    $productPromocodes = $this->safeJsonDecode($fullProductInfo->promocodes_json);
                }

                $orderProducts[] = [
                    'product_id' => $product['product_id'],
                    'title' => $product['product_info']['pagetitle'],
                    'price' => $price,
                    'count' => $quantity,
                    'options' => json_encode([
                        "pharmacy_id" => $orderArray['pharmacy_id'],
                        "iscancellations" => false,
                        "number_1c" => 0,
                        "price" => $price,
                        "price_old" => $price_old,
                        "image" => $productCharachters['image'] ?? null,
                        "promocodes" => $productPromocodes ?? [],
                    ]),
                    'meta' => json_encode([
                        'product_info' => $productCharachters,
                        'promocodes_info' => $productPromocodes,
                    ]),
                    'position' => $position,
                    'image' => $productCharachters['image'] ?? null,
                    'promocodes' => $productPromocodes ?? [],
                    'product_info' => $productCharachters,
                ];
                $position++;
            }
        }

        return [
            'products' => $products,
            'orderProducts' => $orderProducts,
            'sum' => $sum,
            'oldsum' => $oldsum,
            'productsFullInfo' => $productsFullInfo
        ];
    }

    private function calculateOrderTotals(array $cartData, array $orderArray): array
    {
        $sum = $cartData['sum'];
        $oldsum = $cartData['oldsum'];

        // Правильный расчет скидки по промокодам
        $promocodesDiscount = $this->calculatePromocodesDiscount($cartData['products'], $orderArray['promocodes'] ?? []);

        // Применяем промокод к сумме товаров
        $discountedSum = max(0, $sum - $promocodesDiscount);

        // Рассчитываем доставку с учетом скидки по промокоду
        $deliverySum = $this->calculateDeliveryPrice($orderArray, $discountedSum);

        // Итоговая сумма
        $totalSum = $discountedSum + $deliverySum;

        Log::info('Order totals calculated', [
            'original_sum' => $sum,
            'promocodes_discount' => $promocodesDiscount,
            'discounted_sum' => $discountedSum,
            'delivery_sum' => $deliverySum,
            'total_sum' => $totalSum
        ]);

        return [
            'sum' => $sum,
            'oldsum' => $oldsum,
            'promocodesDiscount' => $promocodesDiscount,
            'deliverySum' => $deliverySum,
            'totalSum' => $totalSum,
            'discountAmount' => $oldsum - $sum,
            'oldPricesSaleSum' => ($oldsum > 0) ? round((1 - $sum/$oldsum)*100, 2) : 0
        ];
    }

    private function calculatePromocodesDiscount(array $cartData): float
    {
        $discount = 0;

        if (isset($cartData['cart']['totals']['discount_only_promos'])) {
            $discount = (float) $cartData['cart']['totals']['discount_only_promos'];
            return $discount;
        }

        if (isset($cartData['cart']['products']) && is_array($cartData['cart']['products'])) {
            foreach ($cartData['cart']['products'] as $product) {
                if (isset($product['product_totals']['total_discount_only_promos'])) {
                    $discount += (float) $product['product_totals']['total_discount_only_promos'];
                }
            }
            if ($discount > 0) {
                return $discount;
            }
        }

        $fallbackPaths = [
            'cart.totals.promocode_discount',
            'cart.totals.promo_discount',
            'cart.promocode_discount',
            'totals.discount_only_promos',
            'totals.promocode_discount',
            'promocode_discount'
        ];

        foreach ($fallbackPaths as $path) {
            $pathParts = explode('.', $path);
            $value = $cartData;

            foreach ($pathParts as $part) {
                if (isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    $value = null;
                    break;
                }
            }

            if (is_numeric($value) && $value > 0) {
                $discount = (float) $value;
                break;
            }
        }

        return $discount;
    }

    private function calculateDeliveryPrice(array $orderArray, float $discountedSum): float
    {
        $delivery_method = $orderArray['delivery'];

        if ($delivery_method !== 'delivery' || empty($orderArray['delivery_zone'])) {
            return 0;
        }

        $deliveryZone = $orderArray['delivery_zone'];

        switch ($deliveryZone) {
            case 'yellow':
                return 8;
            case 'green':
                // Для зеленой зоны проверяем сумму ПОСЛЕ применения промокода
                return ($discountedSum >= 40) ? 0 : 8;
            default:
                return 0;
        }
    }

    private function createOrder(array $orderArray, array $calculations): int
    {
        $delivery_method = $orderArray['delivery'];
        $delivery_method_title = ($orderArray['delivery'] == 'self') ? "Самовывоз" : "Доставка";

        $payment_method = $orderArray['payment'];
        $payment_method_title = match($orderArray['payment']) {
            'cash' => "При получении",
            'bepaid' => "Bepaid (Банковская карта)",
            'oplati' => "Oplati",
            'erip' => "ЕРИП",
            default => $orderArray['payment']
        };

        $fields = json_encode([
            "comment" => $orderArray['comment'] ?? '',
            "agree" => true,
            "city" => $orderArray['city'] ?? '',
            "street" => $orderArray['address'] ?? '',
            "entrance" => $orderArray['entrance'] ?? '',
            "floor" => $orderArray['floor'] ?? '',
            "apartment" => $orderArray['apartment'] ?? '',
            "intercom" => $orderArray['intercom'] ?? '',
            "delivery" => [
                "id" => $delivery_method,
                "title" => $delivery_method_title,
                "city" => $orderArray['city'] ?? '',
                "street" => $orderArray['address'] ?? '',
                "entrance" => $orderArray['entrance'] ?? '',
                "floor" => $orderArray['floor'] ?? '',
                "apartment" => $orderArray['apartment'] ?? '',
                "intercom" => $orderArray['intercom'] ?? '',
            ],
            "payment" => [
                "id" => $payment_method,
                "title" => $payment_method_title,
                "caption" => ""
            ],
            "promocodes" => $orderArray['promocodes'] ?? [],
            "sum" => [
                "pricesSum" => $calculations['sum'],
                "oldPricesSum" => $calculations['oldsum'],
                "oldPricesSaleSum" => $calculations['oldPricesSaleSum'],
                "promocodesDiscount" => $calculations['promocodesDiscount'],
                "deliverySum" => $calculations['deliverySum'],
                "totalSum" => $calculations['totalSum']
            ],
            "delivery_method" => $delivery_method,
            "delivery_method_title" => $delivery_method_title,
            "payment_method" => $payment_method,
            "payment_method_title" => $payment_method_title
        ]);

        $order = new EvoCommerceOrders();
        $order->customer_id = auth()->user()->id;
        $order->name = $orderArray['first_name'] . ' ' . $orderArray['last_name'];
        $order->phone = $orderArray['phone'];
        $order->email = $orderArray['email'] ?? '';
        $order->hash = $this->generateUniqueHash();
        $order->status_id = 1;
        $order->fields = $fields;
        $order->lang = 'russian-UTF8';
        $order->currency = 'BYN';
        $order->amount = $calculations['totalSum'];
        $order->save();

        return $order->id;
    }

    private function saveOrderProducts(int $orderId, array $orderProducts, int $pharmacyId): void
    {
        $productsToInsert = collect($orderProducts)->map(function ($product) use ($orderId, $pharmacyId) {
            return [
                'order_id' => $orderId,
                'product_id' => $product['product_id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'count' => $product['count'],
                'options' => $product['options'],
                'meta' => $product['meta'],
                'position' => $product['position'],
                'pharmacy_id' => $pharmacyId,
            ];
        })->all();

        if (!empty($productsToInsert)) {
            // Используем массовую вставку для производительности
            EvoCommerceOrderProducts::insert($productsToInsert);
        }
    }

    private function clearCartProducts(array $productIds): void
    {
        if (!empty($productIds)) {
            // Используем detach с массивом ID для выполнения одного запроса
            auth()->user()->cart->products()->detach($productIds);
        }
    }

    private function createOrderHistory(int $orderId): void
    {
        $history = new EvoCommerceOrderHistory();
        $history->order_id = $orderId;
        $history->status_id = 1;
        $history->comment = '';
        $history->notify = 0;
        $history->user_id = auth()->user()->id;
        $history->created_at = now();
        $history->save();
    }

    private function processOrderPayment(int $orderId, float $totalSum, string $paymentMethod): ?object
    {
        $payment = new EvoCommerceOrderPayments();
        $payment->order_id = $orderId;
        $payment->amount = $totalSum;
        $payment->hash = $this->generateUniqueHash();
        $payment->payment_method = $paymentMethod;
        $payment->meta = '{}';
        $payment->save();

        // Обработка платежей
        return match($paymentMethod) {
            'bepaid' => new Bepaid(),
            'oplati' => new Oplati(),
            'erip' => new EripExpresspay(),
            default => null
        };
    }

    private function updateOrderStatus(int $orderId, int $statusId): void
    {
        $order = EvoCommerceOrders::find($orderId);
        if ($order) {
            $order->status_id = $statusId;
            $order->save();
        }
    }

    private function formatOrderResponse(int $orderId, array $orderArray, array $calculations, ?object $processor, array $orderProducts = []): array
    {
        // Получаем информацию об аптеке
        $pharmacyData = $this->PharmacyService->getPharmacyById($orderArray['pharmacy_id']);
        $pharmacy = $this->extractPharmacyInfo($pharmacyData);

        // Полный адрес доставки
        $fullDeliveryAddress = $this->buildFullDeliveryAddress($orderArray);

        $delivery_method = $orderArray['delivery'];
        $delivery_method_title = ($orderArray['delivery'] == 'self') ? "Самовывоз" : "Доставка";
        $payment_method_title = match($orderArray['payment']) {
            'cash' => "При получении",
            'bepaid' => "Bepaid (Банковская карта)",
            'oplati' => "Oplati",
            'erip' => "ЕРИП",
            default => $orderArray['payment']
        };

        $orderData = (object)[
            'order_id' => $orderId,
            'customer_id' => auth()->user()->id,
            'name' => $orderArray['first_name'] . ' ' . $orderArray['last_name'],
            'phone' => $orderArray['phone'],
            'email' => $orderArray['email'] ?? '',
            'status_title' => 'Обработка',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'pharmacy_name' => $pharmacy->pagetitle ?? 'Unknown Pharmacy',
            'pharmacy_id' => $pharmacy->pharmacy_id ?? null,
            'address' => $pharmacy->address ?? null,
            'prices_sum' => $calculations['sum'],
            'old_prices_sum' => $calculations['oldsum'],
            'old_prices_sale_sum' => $calculations['oldPricesSaleSum'],
            'delivery_sum' => $calculations['deliverySum'],
            'total_sum' => $calculations['totalSum'],
            'promocodes_discount' => $calculations['promocodesDiscount'],
            'promocodes' => $orderArray['promocodes'] ?? [],
            'comment' => $orderArray['comment'] ?? '',
            'delivery_method' => $delivery_method,
            'delivery_method_title' => $delivery_method_title,
            'full_delivery_address' => $fullDeliveryAddress,
            'delivery_entrance' => $orderArray['entrance'] ?? null,
            'delivery_floor' => $orderArray['floor'] ?? null,
            'delivery_apartment' => $orderArray['apartment'] ?? null,
            'delivery_intercom' => $orderArray['intercom'] ?? null,
            'delivery_comment' => $orderArray['comment'] ?? null,
            'payment_method' => $orderArray['payment'],
            'payment_method_title' => $payment_method_title,
            'has_discount' => ($calculations['oldsum'] > $calculations['sum']),
            'discount_amount' => $calculations['discountAmount'],
            'is_delivery' => ($calculations['deliverySum'] > 0),
            'has_promocodes' => ($calculations['promocodesDiscount'] > 0),
            'order_products_json' => $orderProducts,
        ];

        return [
            'order' => $orderData,
            'summary' => [
                'products_price' => $orderData->prices_sum,
                'products_price_old' => $orderData->old_prices_sum,
                'discount_percent' => $orderData->old_prices_sale_sum,
                'discount_amount' => $orderData->discount_amount,
                'promocodes_discount' => $orderData->promocodes_discount,
                'delivery_price' => $orderData->delivery_sum,
                'total_price' => $orderData->total_sum,
            ],
            'delivery_info' => [
                'method' => $orderData->delivery_method,
                'method_title' => $orderData->delivery_method_title,
                'address' => $orderData->full_delivery_address,
                'is_delivery' => $orderData->is_delivery,
                'entrance' => $orderData->delivery_entrance,
                'floor' => $orderData->delivery_floor,
                'apartment' => $orderData->delivery_apartment,
                'intercom' => $orderData->delivery_intercom,
                'comment' => $orderData->delivery_comment,
            ],
            'payment_info' => [
                'method' => $orderData->payment_method,
                'method_title' => $orderData->payment_method_title,
            ],
            'additional' => [
                'comment' => $orderData->comment,
                'has_discount' => $orderData->has_discount,
                'has_promocodes' => $orderData->has_promocodes,
                'promocodes' => $orderData->promocodes,
                'payment_link' => $processor ? $processor->getPaymentLink(
                    EvoCommerceOrders::find($orderId),
                    EvoCommerceOrderPayments::where('order_id', $orderId)->first()
                ) : null
            ]
        ];
    }

    private function extractPharmacyInfo($pharmacyData): ?object
    {
        if (is_array($pharmacyData) && !empty($pharmacyData)) {
            return (object)$pharmacyData[0];
        } elseif (is_object($pharmacyData) && method_exists($pharmacyData, 'first')) {
            return $pharmacyData->first();
        } elseif (is_object($pharmacyData)) {
            return $pharmacyData;
        }
        return null;
    }

    private function buildFullDeliveryAddress(array $orderArray): ?string
    {
        if ($orderArray['delivery'] !== 'delivery') {
            return null;
        }

        $addressParts = [];
        if (!empty($orderArray['city'])) $addressParts[] = $orderArray['city'];
        if (!empty($orderArray['address'])) $addressParts[] = $orderArray['address'];
        if (!empty($orderArray['entrance'])) $addressParts[] = 'подъезд ' . $orderArray['entrance'];
        if (!empty($orderArray['floor'])) $addressParts[] = 'этаж ' . $orderArray['floor'];
        if (!empty($orderArray['apartment'])) $addressParts[] = 'кв. ' . $orderArray['apartment'];
        if (!empty($orderArray['intercom'])) $addressParts[] = 'домофон ' . $orderArray['intercom'];

        return !empty($addressParts) ? implode(', ', $addressParts) : null;
    }

    private function getProductsInfo(array $productIds): Collection
    {
        $uncachedIds = array_diff($productIds, array_keys($this->productCache));

        if (!empty($uncachedIds)) {
            $products = $this->ProductInfoViewJson
                ->query()
                ->select(['product_id', 'product_charachters', 'promocodes_json'])
                ->whereIn('product_id', $uncachedIds)
                ->get();

            foreach ($products as $product) {
                $this->productCache[$product->product_id] = $product;
            }
        }

        return collect($productIds)
            ->map(fn($id) => $this->productCache[$id] ?? null)
            ->filter()
            ->keyBy('product_id');
    }

    /**
     * Безопасное декодирование JSON с проверкой типа данных
     */
    private function safeJsonDecode($data): ?array
    {
        if (is_null($data)) {
            return null;
        }

        if (is_array($data)) {
            return $data;
        }

        if (!is_string($data)) {
            Log::warning('Unexpected data type for JSON decode', [
                'type' => gettype($data),
                'value' => $data
            ]);
            return null;
        }

        $decoded = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('JSON decode error', [
                'error' => json_last_error_msg(),
                'data' => $data
            ]);
            return null;
        }

        return $decoded;
    }

    public function getDetailed(string $userId, int $orderId): Collection
    {
        $orders = $this->OrderViewJson->query()
            ->select('*') // Явно выбираем все поля включая order_products_json
            ->where('customer_id', $userId)
            ->where('id', $orderId)
            ->get();

        if ($orders->isEmpty()) {
            return $orders;
        }

        return $this->enrichOrdersWithProductInfo($orders);
    }

    private function enrichOrdersWithProductInfo(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return $orders;
        }

        $orderIds = $orders->pluck('id')->toArray();
        $orderProductsByOrderId = $this->EvoCommerceOrderProducts->query()
            ->whereIn('order_id', $orderIds)
            ->get()
            ->groupBy('order_id');

        // N+1 Fix: Собираем все ID аптек из всех заказов
        $pharmacyIds = collect($orderProductsByOrderId)->flatten()->map(function ($product) {
            $options = $this->safeJsonDecode($product->options) ?? [];
            return $options['pharmacy_id'] ?? null;
        })->filter()->unique()->toArray();

        // N+1 Fix: Загружаем информацию по всем аптекам одним запросом
        $pharmaciesById = $this->getPharmaciesInfo($pharmacyIds);

        return $orders->transform(function($order) use ($orderProductsByOrderId, $pharmaciesById) {
            $products = $orderProductsByOrderId->get($order->id, collect());

            $order->order_products_json = $products->map(function($product) {
                $options = $this->safeJsonDecode($product->options) ?? [];
                return [
                    'product_id' => $product->product_id,
                    'pharmacy_id' => $options['pharmacy_id'] ?? null,
                    'product_title' => $product->title,
                    'price' => (float) round($product->price, 2),
                    'position' => $product->position,
                    'count' => $product->count,
                    'options' => $options
                ];
            })->toArray();

            $orderFields = $this->safeJsonDecode($order->fields) ?? [];

            $firstPharmacyId = collect($order->order_products_json)
                ->pluck('pharmacy_id')
                ->filter()
                ->first();

            // N+1 Fix: Получаем аптеку из заранее загруженной коллекции
            $pharmacy = $pharmaciesById->get($firstPharmacyId);

            return $this->transformOrderWithEnrichedData($order, $pharmacy, $orderFields, $order->order_products_json);
        });
    }

    private function enrichProductWithFullInfo(array $product, Collection $productsFullInfo): array
    {
        $fullInfo = $productsFullInfo->get($product['product_id']);
        if ($fullInfo) {
            $productCharachters = $this->safeJsonDecode($fullInfo->product_charachters);
            $productPromocodes = $this->safeJsonDecode($fullInfo->promocodes_json);

            $product['image'] = $productCharachters['image'] ?? null;
            $product['promocodes'] = $productPromocodes ?? [];
            $product['product_info'] = $productCharachters;
        }
        return $product;
    }

    private function getPharmacyInfo(?int $pharmacyId): ?object
    {
        if (!$pharmacyId) {
            return null;
        }

        try {
            return DB::table('evo_pharmacies_view')
                ->where('pharmacy_id', $pharmacyId)
                ->first();
        } catch (\Exception $e) {
            Log::warning('Pharmacy info not available', ['pharmacy_id' => $pharmacyId]);
            return null;
        }
    }

    /**
     * N+1 Fix: Загружает информацию по нескольким аптекам одним запросом.
     */
    private function getPharmaciesInfo(array $pharmacyIds): Collection
    {
        if (empty($pharmacyIds)) {
            return collect();
        }

        try {
            return DB::table('evo_pharmacies_view')
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->get()
                ->keyBy('pharmacy_id');
        } catch (\Exception $e) {
            Log::warning('Не получили информацию: ', ['pharmacy_ids' => $pharmacyIds]);
            return collect();
        }
    }

    private function transformOrderWithEnrichedData($order, ?object $pharmacy, array $orderFields, array $enrichedProducts): object
    {
        $order->pharmacy_name = $pharmacy?->pagetitle ?? 'Unknown Pharmacy';
        $order->address = $pharmacy?->address;
        $order->pharmacy_id = $pharmacy?->pharmacy_id;
        $order->order_products_json = $enrichedProducts;

        if (isset($order->created_at)) {
            $order->created_at = $this->formatDateTimeISO($order->created_at);
        }
        if (isset($order->updated_at)) {
            $order->updated_at = $this->formatDateTimeISO($order->updated_at);
        }

        if (!empty($orderFields)) {
            $this->fillOrderFieldsFromJson($order, $orderFields);
        }

        return $order;
    }

    private function formatDateTimeISO($dateTime): string
    {
        if (is_string($dateTime)) {
            $dateTime = \Carbon\Carbon::parse($dateTime);
        }
        return $dateTime->utc()->toISOString();
    }

    private function fillOrderFieldsFromJson($order, array $orderFields): void
    {
        $sumData = $orderFields['sum'] ?? [];

        $order->prices_sum = $sumData['pricesSum'] ?? $order->amount ?? 0;
        $order->old_prices_sum = $sumData['oldPricesSum'] ?? $order->amount ?? 0;
        $order->old_prices_sale_sum = $sumData['oldPricesSaleSum'] ?? 0;
        $order->delivery_sum = $sumData['deliverySum'] ?? 0;
        $order->total_sum = $sumData['totalSum'] ?? $order->amount ?? 0;
        $order->promocodes_discount = $sumData['promocodesDiscount'] ?? 0;

        if ($order->prices_sum == 0 && isset($order->order_products_json) && is_array($order->order_products_json)) {
            $calculatedSum = 0;
            foreach ($order->order_products_json as $product) {
                $calculatedSum += ($product['price'] ?? 0) * ($product['count'] ?? 1);
            }
            if ($calculatedSum > 0) {
                $order->prices_sum = $calculatedSum;
                $order->total_sum = $calculatedSum + $order->delivery_sum;
            }
        }

        $order->promocodes = $orderFields['promocodes'] ?? [];
        $order->comment = $orderFields['comment'] ?? '';

        // Информация о доставке
        if (!empty($orderFields['delivery'])) {
            $this->fillDeliveryInfo($order, $orderFields['delivery'], $orderFields);
        }

        // Информация о способе оплаты
        if (!empty($orderFields['payment'])) {
            $order->payment_method = $orderFields['payment']['id'] ?? $orderFields['payment_method'] ?? null;
            $order->payment_method_title = $orderFields['payment']['title'] ?? $orderFields['payment_method_title'] ?? null;
        }

        // Дополнительные поля
        $order->has_discount = ($order->old_prices_sum > $order->prices_sum);
        $order->discount_amount = $order->old_prices_sum - $order->prices_sum;
        $order->is_delivery = ($order->delivery_sum > 0);
        $order->has_promocodes = ($order->promocodes_discount > 0);
    }

    private function fillDeliveryInfo($order, array $deliveryInfo, array $orderFields): void
    {
        $order->delivery_method = $deliveryInfo['id'] ?? $orderFields['delivery_method'] ?? null;
        $order->delivery_method_title = $deliveryInfo['title'] ?? $orderFields['delivery_method_title'] ?? null;

        // Детализация адреса доставки
        $order->delivery_entrance = $deliveryInfo['entrance'] ?? $orderFields['entrance'] ?? null;
        $order->delivery_floor = $deliveryInfo['floor'] ?? $orderFields['floor'] ?? null;
        $order->delivery_apartment = $deliveryInfo['apartment'] ?? $orderFields['apartment'] ?? null;
        $order->delivery_intercom = $deliveryInfo['intercom'] ?? $orderFields['intercom'] ?? null;
        $order->delivery_comment = $orderFields['comment'] ?? null;

        // Полный адрес доставки
        $addressParts = [];
        if (!empty($deliveryInfo['city'])) $addressParts[] = $deliveryInfo['city'];
        if (!empty($deliveryInfo['street'])) $addressParts[] = $deliveryInfo['street'];
        if (!empty($deliveryInfo['entrance'])) $addressParts[] = 'подъезд ' . $deliveryInfo['entrance'];
        if (!empty($deliveryInfo['floor'])) $addressParts[] = 'этаж ' . $deliveryInfo['floor'];
        if (!empty($deliveryInfo['apartment'])) $addressParts[] = 'кв. ' . $deliveryInfo['apartment'];
        if (!empty($deliveryInfo['intercom'])) $addressParts[] = 'домофон ' . $deliveryInfo['intercom'];

        $order->full_delivery_address = !empty($addressParts) ? implode(', ', $addressParts) : null;
    }

    public function getList(string $userId, ?int $isActive, ?int $number, ?array $delivery): Collection
    {
        $deliveryTitles = $this->getDeliveryTitles($delivery);

        $orders = $this->EvoCommerceOrders->query()
            ->select([
                'id as order_id',
                'id',
                'customer_id',
                'created_at',
                'updated_at',
                'phone',
                'name',
                'email',
                'amount',
                'currency',
                'status_id',
                'fields'
            ])
            ->with(['status:id,title'])
            ->where('customer_id', $userId)
            ->when(!empty($number), fn($query) => $query->where('id', $number))
            ->when((!empty($isActive) && $isActive == 1), fn($query) => $query->whereHas('status', function($q) {
                $q->whereNotIn('title', INACTIVE_STATUSES);
            }))
            ->orderBy('id', 'desc')
            ->get();

        if ($orders->isNotEmpty()) {
            return $this->enrichOrdersWithProductInfo($orders);
        }

        return $orders;
    }

    private function getDeliveryTitles(?array $delivery): array
    {
        if (empty($delivery)) {
            return array_merge(DELIVERY_TITLES, SELF_GET_TITLES);
        }

        $deliveryArCount = count($delivery);

        if ($deliveryArCount == 1 && $delivery[0] == 'Самовывоз') {
            return SELF_GET_TITLES;
        }

        if ($deliveryArCount == 2) {
            return array_merge(DELIVERY_TITLES, SELF_GET_TITLES);
        }

        return DELIVERY_TITLES;
    }

    public function getOrderStatuses(): Collection
    {
        return $this->EvoCommerceOrderStatuses->query()->get();
    }
}
