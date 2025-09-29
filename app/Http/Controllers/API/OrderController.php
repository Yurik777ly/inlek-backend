<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

            $fields = null;
            if (isset($orderArray['fields']) && is_string($orderArray['fields'])) {
                $fields = json_decode($orderArray['fields'], true);
            }

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
                'summary' => [
                    'products_price' => round($fields['sum']['pricesSum'] ?? ($orderData->sum_prices ?? 0), 2),
                    'products_price_old' => round($fields['sum']['oldPricesSum'] ?? ($orderData->sum_prices_old ?? 0), 2),
                    'discount_percent' => round($fields['sum']['oldPricesSaleSum'] ?? ($orderData->sum_prices_sales_old ?? 0), 2),
                    'discount_amount' => round($orderData->discount_amount ?? 0, 2),
                    'promocodes_discount' => round($fields['sum']['promocodesDiscount'] ?? ($orderData->promocodes_discount ?? 0), 2),
                    'delivery_price' => round($fields['sum']['deliverySum'] ?? ($orderData->delivery_sum ?? 0), 2),
                    'total_price' => round($fields['sum']['totalSum'] ?? ($orderData->total_sum ?? 0), 2),
                ],
                'delivery_info' => [
                    'method' => $fields['delivery_method'] ?? ($orderData->delivery_method ?? null),
                    'method_title' => $fields['delivery_method_title'] ?? ($orderData->delivery_method_title ?? null),
                    'address' => $orderData->full_delivery_address ?? null,
                    'is_delivery' => $orderData->is_delivery ?? false,
                    'entrance' => $orderData->delivery_entrance ?? null,
                    'floor' => $orderData->delivery_floor ?? null,
                    'apartment' => $orderData->delivery_apartment ?? null,
                    'intercom' => $orderData->delivery_intercom ?? null,
                    'comment' => $orderData->delivery_comment ?? null,
                ],
                'payment_info' => [
                    'method' => $fields['payment_method'] ?? ($orderData->payment_method ?? null),
                    'method_title' => $fields['payment_method_title'] ?? ($orderData->payment_method_title ?? null),
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

            if (isset($orderArray['fields']) && is_string($orderArray['fields'])) {
                $fields = json_decode($orderArray['fields'], true);
                if ($fields) {
                    $orderArray['total_sum'] = $fields['sum']['totalSum'] ?? 0;
                    $orderArray['delivery_method_title'] = $fields['delivery_method_title'] ?? null;
                    $orderArray['payment_method_title'] = $fields['payment_method_title'] ?? null;
                }
                unset($orderArray['fields']);
            }

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
