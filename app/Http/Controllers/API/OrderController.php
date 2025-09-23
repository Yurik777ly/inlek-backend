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
            'payment' => 'required|string',
            'pharmacy_id' => 'required|int',
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required_if:delivery_method,delivery|nullable|email|max:255',
            'city' => 'required_if:delivery_method,delivery|nullable|max:255',
            'address' => 'required_if:delivery_method,delivery|nullable|max:255',
            'entrance' => 'nullable|string|max:10',
            'floor' => 'nullable|string|max:10',
            'apartment' => 'nullable|string|max:10',
            'intercom' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
            'ids' => 'array',
            'promocodes' => 'array', // Проверка каждого элемента
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Создание заказа
        $data = $this->OrderService->create($validator->validated());

        return $this->responseOk($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getDetailedOrder(Request $request, int $orderId): JsonResponse
    {
        $userId = $request->user()->id;
        $order = $this->OrderService->getDetailed($userId, $orderId);
        return $this->responseOk(data: $order);
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
        return $this->responseOk(data: $orders);
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
            'erip' => env('erip', '0'),
            'bepaid' => env('bepaid', '0'),
            'oplati' => env('oplati', '0'),
        ]);
    }
        
}
