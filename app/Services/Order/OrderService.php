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
use App\Services\Pharmacy\PharmacyService;

use App\Services\Cart\CartService;

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

            auth()->user()->update(
                [
                    'phone' => $orderArray['phone'],
                    'first_name' => $orderArray['first_name'],
                    'last_name' => $orderArray['last_name'],
                    'email' => $orderArray['email'],
                ]
            );

        $delivery_method = $orderArray['delivery'];
        $delivery_method_title = ($orderArray['delivery'] == 'self') ? "Самовывоз" : "Доставка";
        $payment_method = $orderArray['payment'];
        $payment_method_title = ($orderArray['delivery'] == 'cash') ? "При получении" : $payment_method;

        $products = $this->CartService->getCart();

        if(empty($products)) return false;

        $position = 1;
        $cartProducts = [];

        $sum = 0;
        $oldsum = 0;

        $orderArray['pharmacy_id'] = ($orderArray['pharmacy_id'] == 0) ? 6864 : $orderArray['pharmacy_id'];

        $orderProducts = [];
        foreach ($products['product_info'] as $cartProduct) {
            if (in_array($cartProduct['product_charachters']['product_id'], $orderArray['ids'])) {
                $price = (float)$cartProduct['product_charachters']['product_price_from'];
                $price_old = $cartProduct['product_charachters']['product_price_from_old'] ?? 0;
                $sum += $price * $cartProduct['quantity'];
                $oldsum+= ($price_old > 0) ? $price_old*$cartProduct['quantity'] : $price*$cartProduct['quantity'];

                $orderProducts[$position-1] = [
                    'product_id' => $cartProduct['product_charachters']['product_id'],
                    'title' => $cartProduct['product_charachters']['pagetitle'],
                    'price' => $price,
                    'count' => $cartProduct['quantity'],
                    'options' => "{\"pharmacy_id\":{$orderArray['pharmacy_id']},\"iscancellations\":false,\"number_1c\":0,\"price\":{$price},\"price_old\":{$price_old}}",
                    'meta' => null,
                    'position' => $position,
                ];
                $position++;
            }
        }

        $deliverySum = 0;

            $fields = json_encode([
                "comment" => $orderArray['comment'] ?? '',
                "agree" => true,
                "city" => $orderArray['city'] ?? '',
                "street" => $orderArray['address'] ?? '',
                "entrance"=> $orderArray['entrance'] ?? '',
                "floor"=> $orderArray['floor'] ?? '',
                "apartment"=> $orderArray['apartment'] ?? '',
                "delivery" => [
                    "id"=>$delivery_method,
                    "title"=>$delivery_method_title,
                    "city" => $orderArray['city'] ?? '',
                    "street" => $orderArray['address'] ?? '',
                    "entrance"=> $orderArray['entrance'] ?? '',
                    "floor"=> $orderArray['floor'] ?? '',
                    "apartment"=> $orderArray['apartment'] ?? '',
                ],
                "payment" => ["id"=>$payment_method,"title"=>$payment_method_title,"caption"=>""],
                "sum" => [
                    "pricesSum" => $sum,    //стоимость товаров со скидкой
                    "oldPricesSum" => $oldsum, //стоимость товаров без скидки
                    "oldPricesSaleSum" => ($oldsum > 0) ? round((1 - $sum/$oldsum)*100,2) : 0, //скидка
                    "deliverySum" => $deliverySum, //доставка
                    "totalSum" => $sum + $deliverySum // итого
                ],
                "delivery_method" => $delivery_method,
                "delivery_method_title" => $delivery_method_title,
                "payment_method" => $payment_method,
                "payment_method_title" => $payment_method_title
            ]);

        $this->EvoCommerceOrders->customer_id = auth()->user()->id;
        $this->EvoCommerceOrders->name = $orderArray['first_name'] . ' ' . $orderArray['last_name'];
        $this->EvoCommerceOrders->phone = $orderArray['phone'];
        $this->EvoCommerceOrders->email = $orderArray['email'];
        $this->EvoCommerceOrders->hash = $this->generateUniqueHash();
        $this->EvoCommerceOrders->status_id = 1;
        $this->EvoCommerceOrders->fields = $fields;
        $this->EvoCommerceOrders->lang = 'russian-UTF8';
        $this->EvoCommerceOrders->currency = 'BYN';
        $this->EvoCommerceOrders->amount = $sum;

        $this->EvoCommerceOrders->save();
        $order_id =  $this->EvoCommerceOrders->id;


        foreach($orderProducts as $orderProduct) {
                $orderProduct['order_id'] = $order_id;
                $productObj = app()->make(EvoCommerceOrderProducts::class);
                $productObj->fill($orderProduct)->save(); // Заполнение + сохранение
                unset($productObj);
                auth()->user()->cart->products()->detach($orderProduct['product_id']);
        }

        $this->EvoCommerceOrderHistory->order_id = $order_id;
        $this->EvoCommerceOrderHistory->status_id = 1;
        $this->EvoCommerceOrderHistory->comment = '';
        $this->EvoCommerceOrderHistory->notify = 0;
        $this->EvoCommerceOrderHistory->user_id = auth()->user()->id;
        $this->EvoCommerceOrderHistory->created_at = date('Y-m-d H:i:s');
        $this->EvoCommerceOrderHistory->save();


        $this->EvoCommerceOrderPayments->order_id = $order_id;
        $this->EvoCommerceOrderPayments->amount = $sum;
        $this->EvoCommerceOrderPayments->hash = $this->generateUniqueHash();
        $this->EvoCommerceOrderPayments->payment_method = $payment_method;
        $this->EvoCommerceOrderPayments->meta = '{}';
        $this->EvoCommerceOrderPayments->save();

        $ulr = '';

        $processor = null;
        switch($payment_method) {
            case 'bepaid':
                $processor = new Bepaid();
            break;
            default:
                $processor = null;
            break;
        }

        $this->EvoCommerceOrders->status_id = 2;
        $this->EvoCommerceOrders->save();

        $data = [];

        $data['order_id'] = $order_id;
        $data['created_at'] = $this->EvoCommerceOrderHistory->created_at;
        $data['user_id'] = auth()->user()->id;
        $data['delivery_method'] = $delivery_method;
        $data['payment_method'] = $payment_method;
        $data['city'] = $orderArray['city']  ?? '';
        $data['address'] = $orderArray['address'] ?? '';
        if(!empty($data['address'])) {
            $orderArray['entrance']     = empty($orderArray['entrance'])    ? '':' подъезд '.$orderArray['entrance'];
            $orderArray['floor']        = empty($orderArray['floor'])       ? '':' этаж '.$orderArray['floor'];
            $orderArray['apartment']    = empty($orderArray['apartment'])   ? '':' квартира '.$orderArray['apartment'];
            $orderArray['intercom']     = empty($orderArray['intercom'])    ? '':' домофон '.$orderArray['intercom'];

            $data['address'] .= $orderArray['entrance'];
            $data['address'] .= $orderArray['floor'];
            $data['address'] .= $orderArray['apartment'];
            $data['address'] .= $orderArray['intercom'];
        }

        $pharmacy_id = ($orderArray['pharmacy_id'] == 0) ? 6864 : $orderArray['pharmacy_id'];
        $data['pharmacy'] = $this->PharmacyService->getPharmacyById($pharmacy_id);


        if(!$processor) {
            $data['link'] = '';
        } else {
            $data['link'] = $processor->getPaymentLink($this->EvoCommerceOrders, $this->EvoCommerceOrderPayments);
        }

        return $data;


/*
    "city":"Минск",
    "address": "Хрущева"
*/
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

        $firstProduct = $orders->first()->order_products_json;

        $firstPharmacyId = collect($firstProduct)
            ->pluck('pharmacy_id')
            ->filter()
            ->first();

        $pharmacyName = $firstPharmacyId
            ? DB::table('evo_pharmacies_view')
                ->where('pharmacy_id', $firstPharmacyId)
                ->value('pagetitle')
            : null;

        $orders->transform(function($order) use ($pharmacyName) {
            $order->pharmacy_name = $pharmacyName;
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


}
