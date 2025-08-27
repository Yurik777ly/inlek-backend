<?php

namespace App\Services\Order;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    ) {}


    public function generateUniqueHash() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s%s%s%s%s%s%s', str_split(bin2hex($data), 4));
    }

    public function pay($payhash)
    {
        $payment = $this->EvoCommerceOrderPayments->query()->where('hash', $payhash)->first();
        if($payment) {
            $payment->paid = 1;
            $payment->save();
        }
    }

    public function create($orderArray)
    {
        auth()->user()->update([
            'phone' => $orderArray['phone'],
            'first_name' => $orderArray['first_name'],
            'last_name' => $orderArray['last_name'],
            'email' => $orderArray['email'] ?? null,
        ]);

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

        $orderArray['pharmacy_id'] = ($orderArray['pharmacy_id'] == 0) ? 6864 : $orderArray['pharmacy_id'];

        $cartDTO = new CartDetailedDTO(
            pharmacyId: $orderArray['pharmacy_id'],
            deliveryZone: $orderArray['delivery_zone'] ?? null,
            promocodes: implode(',', $orderArray['promocodes'] ?? [])
        );

        $this->CartService->setUserGeo('', '');
        $products = $this->CartService->getCartDetailed($cartDTO);

        if(empty($products)) return false;

        $position = 1;
        $sum = 0;
        $oldsum = 0;
        $orderProducts = [];

        foreach ($products['cart']['products'] as $product) {
            $cartProduct = $product['product_info'];
            if (in_array($product['product_id'], $orderArray['ids'])) {
                $price = (float)$product['product_totals']['total'];
                $price_old = (float)$product['product_totals']['total_old'] ?? 0;
                $sum += $price * $product['quantity'];
                $oldsum += ($price_old > 0) ? $price_old * $product['quantity'] : $price * $product['quantity'];

                $orderProducts[$position-1] = [
                    'product_id' => $product['product_id'],
                    'title' => $cartProduct->pagetitle ?? 'Unknown Product',
                    'price' => $price,
                    'count' => $product['quantity'],
                    'options' => "{\"pharmacy_id\":{$orderArray['pharmacy_id']},\"iscancellations\":false,\"number_1c\":0,\"price\":{$price},\"price_old\":{$price_old}}",
                    'meta' => null,
                    'position' => $position,
                ];
                $position++;
            }
        }

        // Расчет стоимости доставки
        $deliverySum = 0;
        if ($delivery_method == 'delivery' && !empty($orderArray['delivery_zone'])) {
            if ($orderArray['delivery_zone'] == 'yellow') {
                $deliverySum = 8;
            } else if ($orderArray['delivery_zone'] == 'green') {
                if ($oldsum >= 40) {
                    $deliverySum = 0;
                } else {
                    $deliverySum = 8;
                }
            }
        }

        $totalSum = $sum + $deliverySum;

        // Расширенный JSON с полями
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
                "pricesSum" => $sum,
                "oldPricesSum" => $oldsum,
                "oldPricesSaleSum" => ($oldsum > 0) ? round((1 - $sum/$oldsum)*100, 2) : 0,
                "deliverySum" => $deliverySum,
                "totalSum" => $totalSum
            ],
            "delivery_method" => $delivery_method,
            "delivery_method_title" => $delivery_method_title,
            "payment_method" => $payment_method,
            "payment_method_title" => $payment_method_title
        ]);

        // Создание заказа
        $this->EvoCommerceOrders->customer_id = auth()->user()->id;
        $this->EvoCommerceOrders->name = $orderArray['first_name'] . ' ' . $orderArray['last_name'];
        $this->EvoCommerceOrders->phone = $orderArray['phone'];
        $this->EvoCommerceOrders->email = $orderArray['email'] ?? '';
        $this->EvoCommerceOrders->hash = $this->generateUniqueHash();
        $this->EvoCommerceOrders->status_id = 1;
        $this->EvoCommerceOrders->fields = $fields;
        $this->EvoCommerceOrders->lang = 'russian-UTF8';
        $this->EvoCommerceOrders->currency = 'BYN';
        $this->EvoCommerceOrders->amount = $totalSum;

        $this->EvoCommerceOrders->save();
        $order_id = $this->EvoCommerceOrders->id;

        // Сохранение продуктов заказа
        foreach($orderProducts as $orderProduct) {
            $orderProduct['order_id'] = $order_id;
            $productObj = app()->make(EvoCommerceOrderProducts::class);
            $productObj->fill($orderProduct)->save();
            unset($productObj);
            auth()->user()->cart->products()->detach($orderProduct['product_id']);
        }

        // История заказа
        $this->EvoCommerceOrderHistory->order_id = $order_id;
        $this->EvoCommerceOrderHistory->status_id = 1;
        $this->EvoCommerceOrderHistory->comment = '';
        $this->EvoCommerceOrderHistory->notify = 0;
        $this->EvoCommerceOrderHistory->user_id = auth()->user()->id;
        $this->EvoCommerceOrderHistory->created_at = date('Y-m-d H:i:s');
        $this->EvoCommerceOrderHistory->save();

        // Платеж
        $this->EvoCommerceOrderPayments->order_id = $order_id;
        $this->EvoCommerceOrderPayments->amount = $totalSum;
        $this->EvoCommerceOrderPayments->hash = $this->generateUniqueHash();
        $this->EvoCommerceOrderPayments->payment_method = $payment_method;
        $this->EvoCommerceOrderPayments->meta = '{}';
        $this->EvoCommerceOrderPayments->save();

        // Обработка платежей
        $processor = match($payment_method) {
            'bepaid' => new Bepaid(),
            'oplati' => new Oplati(),
            'erip' => null,
            default => null
        };
        $ulr = '';

        $processor = null;
        switch($payment_method) {
            case 'bepaid':
                $processor = new Bepaid();
            break;
            case 'oplati':
                $processor = new Oplati();
            break;
            case 'erip':
                $processor = new EripExpresspay();
            break;
            default:
                $processor = null;
            break;
        }

        $this->EvoCommerceOrders->status_id = 2;
        $this->EvoCommerceOrders->save();

        // Формирование ответа
        $data = [
            'order_id' => $order_id,
            'created_at' => $this->EvoCommerceOrderHistory->created_at,
            'user_id' => auth()->user()->id,
            'delivery_method' => $delivery_method,
            'payment_method' => $payment_method,
            'city' => $orderArray['city'] ?? '',
            'address' => $this->buildFullAddress($orderArray),
        ];

        $pharmacy_id = ($orderArray['pharmacy_id'] == 0) ? 6864 : $orderArray['pharmacy_id'];
        $data['pharmacy'] = $this->PharmacyService->getPharmacyById($pharmacy_id);

        $data['link'] = $processor ? $processor->getPaymentLink($this->EvoCommerceOrders, $this->EvoCommerceOrderPayments) : '';

        return $data;
    }

    public function getDetailed(string $userId, int $orderId)
    {
        $orders = $this->OrderViewJson->query()
            ->where('customer_id', $userId)
            ->where('order_id', $orderId)
            ->get();

        if ($orders->isEmpty()) {
            return $orders;
        }

        $firstOrder = $orders->first();
        $firstProduct = $firstOrder->order_products_json;

        $orderFields = json_decode($firstOrder->fields, true);

        $firstPharmacyId = collect($firstProduct)
            ->pluck('pharmacy_id')
            ->filter()
            ->first();

        $firstPharmacy = $firstPharmacyId ? DB::table('evo_pharmacies_view')
            ->where('pharmacy_id', $firstPharmacyId)
            ->first() : null;

        $orders->transform(function($order) use ($firstPharmacy, $orderFields) {
            $order->pharmacy_name = $firstPharmacy?->pagetitle;

            $order->address = $firstPharmacy?->address;

            $order->pharmacy_id = $firstPharmacy?->pharmacy_id;

            if (!empty($orderFields)) {
                $order->prices_sum = $orderFields['sum']['pricesSum'] ?? 0; // стоимость товаров со скидкой
                $order->old_prices_sum = $orderFields['sum']['oldPricesSum'] ?? 0; // стоимость товаров без скидки
                $order->old_prices_sale_sum = $orderFields['sum']['oldPricesSaleSum'] ?? 0; // процент скидки
                $order->delivery_sum = $orderFields['sum']['deliverySum'] ?? 0; // стоимость доставки
                $order->total_sum = $orderFields['sum']['totalSum'] ?? 0; // итоговая сумма

                // Информация о промокодах (если есть)
                $order->promocodes_discount = $orderFields['sum']['promocodesDiscount'] ?? 0;

                // Комментарий к заказу
                $order->comment = $orderFields['comment'] ?? '';

                // Дополнительная информация о доставке
                if (!empty($orderFields['delivery'])) {
                    $deliveryInfo = $orderFields['delivery'];
                    $order->delivery_method = $deliveryInfo['id'] ?? $orderFields['delivery_method'] ?? null;
                    $order->delivery_method_title = $deliveryInfo['title'] ?? $orderFields['delivery_method_title'] ?? null;

                    // Полный адрес доставки
                    $addressParts = [];
                    if (!empty($deliveryInfo['city'])) $addressParts[] = $deliveryInfo['city'];
                    if (!empty($deliveryInfo['street'])) $addressParts[] = $deliveryInfo['street'];
                    if (!empty($deliveryInfo['entrance'])) $addressParts[] = 'подъезд ' . $deliveryInfo['entrance'];
                    if (!empty($deliveryInfo['floor'])) $addressParts[] = 'этаж ' . $deliveryInfo['floor'];
                    if (!empty($deliveryInfo['apartment'])) $addressParts[] = 'кв. ' . $deliveryInfo['apartment'];

                    $order->full_delivery_address = !empty($addressParts) ? implode(', ', $addressParts) : null;
                }

                // Информация о способе оплаты
                if (!empty($orderFields['payment'])) {
                    $order->payment_method = $orderFields['payment']['id'] ?? $orderFields['payment_method'] ?? null;
                    $order->payment_method_title = $orderFields['payment']['title'] ?? $orderFields['payment_method_title'] ?? null;
                }

                // Дополнительные поля для удобства фронтенда
                $order->has_discount = ($order->old_prices_sum > $order->prices_sum);
                $order->discount_amount = $order->old_prices_sum - $order->prices_sum;
                $order->is_delivery = ($order->delivery_sum > 0);
                $order->has_promocodes = ($order->promocodes_discount > 0);
            }

            return $order;
        });

        return $orders;
    }

    /**
     * @param $id
     * @return Collection
     */
    public function getList(string $userId, ?int $isActive, ?int $number, ?array $delivery): Collection
    {
        $deliveryTitles = DELIVERY_TITLES;
        if (!empty($delivery)) {
            $deliveryArCount = count($delivery);

            if ($deliveryArCount == 1 && $delivery[0] == 'Самовывоз') {
                $deliveryTitles = SELF_GET_TITLES;
            }
            else if ($deliveryArCount == 2) {
                $deliveryTitles = array_merge(DELIVERY_TITLES, SELF_GET_TITLES);
                //dd($deliveryTitles);
            }
        }

        return
            $this->OrderViewJson->query()
                ->where('customer_id', $userId)
                ->when(!empty($number), function($query) use($number) {
                    return $query->where('order_id', $number);
                })
                ->when((!empty($isActive) && $isActive == 1), function($query) use($isActive) {
                    return $query->whereNotIn('status_title', INACTIVE_STATUSES);
                })
                ->when((!empty($delivery)), function($query) use($deliveryTitles) {
                    return $query->whereIn('delivery_method_title', $deliveryTitles);
                })
                ->get();

    }

    /**
     * @return Collection
     */
    public function getOrderStatuses(): Collection
    {
        return $this->EvoCommerceOrderStatuses->query()->get();
    }


    /**
     * Построение полного адреса
     */
    private function buildFullAddress(array $orderArray): string
    {
        if (empty($orderArray['address'])) {
            return '';
        }

        $address = $orderArray['address'];
        $addressParts = [];

        if (!empty($orderArray['entrance'])) {
            $addressParts[] = 'подъезд ' . $orderArray['entrance'];
        }
        if (!empty($orderArray['floor'])) {
            $addressParts[] = 'этаж ' . $orderArray['floor'];
        }
        if (!empty($orderArray['apartment'])) {
            $addressParts[] = 'квартира ' . $orderArray['apartment'];
        }
        if (!empty($orderArray['intercom'])) {
            $addressParts[] = 'домофон ' . $orderArray['intercom'];
        }

        if (!empty($addressParts)) {
            $address .= ' (' . implode(', ', $addressParts) . ')';
        }

        return $address;
    }
}
