<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\EVO\EvoCommerceOrders;
use App\Models\EVO\EvoCommerceOrderPayments;

class OrderController extends Controller
{
    public function __construct(
        protected readonly OrderService $OrderService,
    ) {}

    public function prepareOrder(Request $request)
    {
        // Валидация данных
        $validator = Validator::make($request->all(), [
            'delivery' => 'required|string|in:self,delivery',
            'delivery_zone' => 'string|in:yellow,green',
            'payment' => 'required|string|in:cash,bepaid,oplati,erip',
            'pharmacy_id' => 'required|int',
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required_if:payment,bepaid,oplati,erip|nullable|email|max:255',
            'city' => 'required_if:delivery,delivery|nullable|max:255',
            'address' => 'required_if:delivery,delivery|nullable|max:255',
            'entrance' => 'nullable|string|max:10',
            'floor' => 'nullable|string|max:10',
            'apartment' => 'nullable|string|max:10',
            'intercom' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:evo_site_content,id',
            'promocodes' => 'nullable|array',
            'promocodes.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
    

        // Создание заказа
        $data = $this->OrderService->create($validator->validated());

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Не удалось создать заказ. Проверьте корзину.'
            ], 400);
        }

        if (is_array($data) && isset($data['order']->created_at)) {
            $data['order']->created_at = $this->formatDateTimeISO($data['order']->created_at);
        }
        if (is_array($data) && isset($data['order']->updated_at)) {
            $data['order']->updated_at = $this->formatDateTimeISO($data['order']->updated_at);
        }

        return response()->json([
            'data' => $data,
            'status' => 'success',
        ], 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getDetailedOrder(Request $request, int $orderId): JsonResponse
    {
        $userId = $request->user()->id;
        $order = $this->OrderService->getDetailed($userId, $orderId);

        if (!$order->isEmpty()) {
            $orderData = $order->first();
            $orderArray = $orderData->toArray();
            $processor = $this->OrderService->createPaymentProcess($orderArray);
           

            $fields = $this->decodeOrderFields($orderArray);

            if (isset($orderArray['created_at'])) {
                $orderArray['created_at'] = $this->formatDateTimeISO($orderArray['created_at']);
            }
            if (isset($orderArray['updated_at'])) {
                $orderArray['updated_at'] = $this->formatDateTimeISO($orderArray['updated_at']);
            }

            $orderArray = $this->OrderService->roundOrderFields($orderArray);
           
            $response = [
                'order' => $orderArray,
                'products' => $orderData->order_products_json ?? [],
                'summary' => $this->buildOrderSummary($orderData, $orderArray, $fields),
                'delivery_info' => [
                    'method' => (is_array($fields) ? ($fields['delivery_method'] ?? null) : null)
                        ?? $orderArray['delivery_method']
                        ?? ($orderData->delivery_method ?? null),
                    'method_title' => (is_array($fields) ? ($fields['delivery_method_title'] ?? null) : null)
                        ?? $orderArray['delivery_method_title']
                        ?? ($orderData->delivery_method_title ?? null),
                    'address' => $orderData->full_delivery_address ?? null,
                    'is_delivery' => $orderData->is_delivery ?? false,
                    'entrance' => $orderData->delivery_entrance ?? null,
                    'floor' => $orderData->delivery_floor ?? null,
                    'apartment' => $orderData->delivery_apartment ?? null,
                    'intercom' => $orderData->delivery_intercom ?? null,
                    'comment' => $orderData->delivery_comment ?? null,
                ],
                'payment_info' => [
                    'method' => (is_array($fields) ? ($fields['payment_method'] ?? null) : null)
                        ?? $orderArray['payment_method']
                        ?? ($orderData->payment_method ?? null),
                    'method_title' => (is_array($fields) ? ($fields['payment_method_title'] ?? null) : null)
                        ?? $orderArray['payment_method_title']
                        ?? ($orderData->payment_method_title ?? null),
                    'payment_link' => $processor !== null ? $processor->getPaymentLink(
                        EvoCommerceOrders::find($orderId),
                        EvoCommerceOrderPayments::where('order_id', $orderId)->first()
                    ) : null
                ],
                'additional' => [
                    'comment' => $orderData->comment ?? '',
                    'has_discount' => $orderData->has_discount ?? false,
                    'has_promocodes' => $orderData->has_promocodes ?? false,
                    'promocodes' => $this->extractPromocodes($orderData, $fields),
                ]
            ];

            return $this->responseOk(data: $response);
        }

        return $this->responseOk(data: $order);
    }

    private function extractPromocodes($orderData, ?array $fields): array
    {
        $promocodes = [];

        if ($fields && isset($fields['promocodes'])) {
            $promocodes = is_array($fields['promocodes']) ? $fields['promocodes'] : [$fields['promocodes']];
        }

        if (empty($promocodes) && isset($orderData->promocodes)) {
            if (is_string($orderData->promocodes)) {
                $promocodes = array_filter(explode('|', $orderData->promocodes));
                if (empty($promocodes)) {
                    $promocodes = array_filter(explode(',', $orderData->promocodes));
                }
            } elseif (is_array($orderData->promocodes)) {
                $promocodes = $orderData->promocodes;
            }
        }

        return array_values(array_filter(array_map('trim', $promocodes)));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrders(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $request->validate([
            'delivery' => 'nullable|array',
            'number' => 'nullable|int',
            'is_active' => 'nullable|int',
        ]);
        $orders = $this->OrderService->getList($userId, $validated['is_active'] ?? null, $validated['number'] ?? null, $validated['delivery'] ?? null);

        $transformedOrders = $orders->map(function($order) {
            $orderArray = $order->toArray();

            if (isset($order->status)) {
                $orderArray['status_title'] = $order->status->title;
            }

            $fields = $this->decodeOrderFields($orderArray);
            if ($fields) {
                $sum = $fields['sum'] ?? [];
                $amount = (float) ($orderArray['amount'] ?? 0);
                $orderArray['sum_prices'] = (float) ($sum['pricesSum'] ?? $amount);
                $orderArray['sum_prices_old'] = (float) ($sum['oldPricesSum'] ?? 0);
                $orderArray['sum_prices_sales_old'] = (float) ($sum['oldPricesSaleSum'] ?? 0);
                $orderArray['delivery_sum'] = (float) ($sum['deliverySum'] ?? 0);
                $orderArray['total_sum'] = (float) ($sum['totalSum'] ?? $amount);
                $orderArray['delivery_method_title'] = $fields['delivery_method_title'] ?? null;
                $orderArray['payment_method_title'] = $fields['payment_method_title'] ?? null;
            } else {
                $amount = (float) ($orderArray['amount'] ?? 0);
                $orderArray['sum_prices'] = $amount;
                $orderArray['total_sum'] = $amount;
            }
            unset($orderArray['fields']);

            if (isset($orderArray['created_at'])) {
                $orderArray['created_at'] = $this->formatDateTimeISO($orderArray['created_at']);
            }
            if (isset($orderArray['updated_at'])) {
                $orderArray['updated_at'] = $this->formatDateTimeISO($orderArray['updated_at']);
            }

            return $orderArray;
        });

        return $this->responseOk(data: $transformedOrders);
    }

    private function decodeOrderFields(?array $orderArray): ?array
    {
        if ($orderArray === null || !isset($orderArray['fields'])) {
            return null;
        }

        $fields = $orderArray['fields'];

        if (is_string($fields)) {
            $decoded = json_decode($fields, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($fields) ? $fields : null;
    }

    private function buildOrderSummary(object $orderData, array $orderArray, ?array $fields): array
    {
        $sum = is_array($fields) ? ($fields['sum'] ?? []) : [];
        $amount = (float) ($orderArray['amount'] ?? $orderData->amount ?? 0);

        $productsPrice = (float) (
            $sum['pricesSum']
            ?? $orderArray['sum_prices']
            ?? $orderData->sum_prices
            ?? $orderData->prices_sum
            ?? $amount
        );

        $productsPriceOld = (float) (
            $sum['oldPricesSum']
            ?? $orderArray['sum_prices_old']
            ?? $orderData->sum_prices_old
            ?? $orderData->old_prices_sum
            ?? 0
        );

        $deliveryPrice = (float) (
            $sum['deliverySum']
            ?? $orderArray['delivery_sum']
            ?? $orderData->delivery_sum
            ?? 0
        );

        $totalPrice = (float) (
            $sum['totalSum']
            ?? $orderArray['total_sum']
            ?? $orderData->total_sum
            ?? $amount
        );

        return [
            'products_price' => round($productsPrice, 2),
            'products_price_old' => round($productsPriceOld, 2),
            'discount_percent' => round((float) (
                $sum['oldPricesSaleSum']
                ?? $orderArray['sum_prices_sales_old']
                ?? $orderData->sum_prices_sales_old
                ?? $orderData->old_prices_sale_sum
                ?? 0
            ), 2),
            'discount_amount' => round((float) ($orderData->discount_amount ?? 0), 2),
            'promocodes_discount' => round((float) (
                $sum['promocodesDiscount']
                ?? $orderData->promocodes_discount
                ?? 0
            ), 2),
            'delivery_price' => round($deliveryPrice, 2),
            'total_price' => round($totalPrice, 2),
        ];
    }

    private function formatDateTimeISO($dateTime): string
    {
        if (is_string($dateTime)) {
            $dateTime = \Carbon\Carbon::parse($dateTime);
        }
        return $dateTime->utc()->toISOString();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOrderStatuses(Request $request): JsonResponse
    {
        return $this->responseOk(data: $this->OrderService->getOrderStatuses());
    }

    public function paymentSuccess(Request $request, $payment_id): JsonResponse
    {
        return $this->responseOk();
    }

    public function paymentFailed(Request $request, $payment_id): JsonResponse
    {
        return $this->responseOk();
    }

    public function paymentProcess(Request $request, $payment_id): JsonResponse
    {
        return $this->responseOk();
    }

    public function payment(Request $request): JsonResponse
    {
        if(!empty($request->token))
        {
            $this->OrderService->pay($request->token);
        }
        //file_put_contents('payment_get.txt', var_export($_GET, 1));
        //file_put_contents('payment_post.txt', var_export($_POST, 1));

        return $this->responseOk();
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        return $this->responseOk([
            'erip' => env('ERIP_ENABLED', '0'),
            'bepaid' => env('BEPAID_ENABLED', '0'),
            'oplati' => env('OPLATI_ENABLED', '0'),
        ]);
    }
}
