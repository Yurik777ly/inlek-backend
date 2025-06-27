<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="apteka-online.by API Documentation",
 *     version="0.1",
 * ),
 *  @OA\Server(
 *      url="https://test.mobile.apteka-online.by/api/"
 *  ),
 */
class Controller
{
    public const DEFAULT_HEADERS = [
        'Content-Type' => 'application/json;charset=UTF-8',
        'Charset' => 'utf-8',
    ];

    public function response(              
        mixed $data = [], //string, array, null
        int $code = 200,
        array $headers = []
    ): JsonResponse {
        (bool) $success = (int) $code >= 200 && (int) $code < 300;

        $resultData = ['success' => $success];

        if (! empty($data)) {
            $resultData['data'] = $data;
        }

        $headers = empty($headers) ? self::DEFAULT_HEADERS : $headers;

        //used laravel helper
        return response()->json(
            data: $resultData,
            status: $code,
            headers: $headers,
            options: JSON_UNESCAPED_UNICODE,
        );
    }

    public function responseOk(
        mixed $data = [], //string, array, null
    ): JsonResponse {
        return $this->response($data, 200);
    }
}
