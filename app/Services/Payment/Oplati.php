<?php

namespace App\Services\Payment;

use Exception;
use Illuminate\Support\Facades\Log;

class Oplati extends Payment
{
    public function __construct(array $params = [])
    {
        parent::__construct($params);

        $this->settings = [
            'base_url'   => 'https://oplati-cashboxapi.lwo-dev.by/ms-pay',
            'pay_base'   => 'https://pay.o-plati.by/order',
            'reg_num'    => 'OPL000013550',
            'password'   => '1DkMfaRM2',
            'secret_key' => 'MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAtuxHWPNUp0MQlz+bxf6G3Viu9+0DcKNuskM9AC7dLHl6x/PmijETHo4m1/+Dr25Xm1xgyhFM29eVW/0/W7Ug8vtEobVFa+7eNI+kJUNuZKNBZ6ksBR92m/9ktYVpq3axfbb5U/dGnzccEaWP7/h5rmphjq4Rj2Kwlk5ByJa74ywFnbD7I4JQGrYHIITnrCzvfgZm354jIvr1kaYByFdliB6SN9LI2wWJvY4/FF6RvC1JjirOkdhIzb0M1bgrTkxspDwd6I1W0YDnF08erPLjDtr/hbjXU4s2f1pP2TdlXC4of5c6WN2093K4hTmarC0Y3Mw6qZs3yYYDtsHDZcV8fMi89OlTy5A4B7MtDZ0pztF8Po/c5sgDfbbSt2zjCiUlAjCI19WcObtfXu4OMJUyauI/SZcQ6s8VMoKeCqq8RbVhA9uuMoUq74TouSiSPbpXJ6KdOu4jh8cUmaAZbdDpmHeGdiPfdtrbCRroYW/OYtf7F0hkjnQePEtzLbiuEslTAgMBAAE=',
            'timeout'    => 30,
        ];
    }

    /**
     * Получаем ссылку для оплаты
     */
    public function getPaymentLink($order = '', $payment = '')
    {
        $payloadV2 = [
            'shift'        => '1',
            'sum'          => $payment->amount,
            'orderNumber'  => (string)($order->id . '-' . $payment->id),
            'details'      => [
                'title'       => 'Оплата заказа #' . $order->id,
                'amountTotal' => $payment->amount,
                'items'       => [
                    [
                        'type'     => 1,
                        'name'     => 'Заказ #' . $order->id,
                        'quantity' => 1,
                        'unit'     => 'шт',
                        'price'    => $payment->amount,
                        'cost'     => $payment->amount,
                    ],
                ],
            ],
            'successUrl'      => rtrim(config('app.url'), '/') . '/api/payments/payment-success?token=' . $payment->hash,
            'failureUrl'      => rtrim(config('app.url'), '/') . '/api/payments/payment-failed?token=' . $payment->hash,
            'notificationUrl' => rtrim(config('app.url'), '/') . '/api/payments/payment-process?token=' . $payment->hash,
        ];
        $payloadV2['signature'] = $this->makeSignature($payloadV2);

        $response = $this->request('/pos/webPayments/v2', $payloadV2);

        $hasUsableLink = is_array($response) && (!empty($response['paymentUrl']) || !empty($response['redirectUrl']) || !empty($response['order']['id']) || !empty($response['paymentId']));

        if (!$hasUsableLink) {
            $amountMajor = (float) round($payment->amount, 2);
            $payloadV1 = [
                'shift'        => '1',
                'sum'          => $amountMajor,
                'orderNumber'  => (string)($order->id . '-' . $payment->id),
                'details'      => [
                    'title'       => 'Оплата заказа #' . $order->id,
                    'amountTotal' => $amountMajor,
                    'items'       => [
                        [
                            'type'     => 1,
                            'name'     => 'Заказ #' . $order->id,
                            'quantity' => 1,
                            'unit'     => 'шт',
                            'price'    => $amountMajor,
                            'cost'     => $amountMajor,
                        ],
                    ],
                ],
                'successUrl'      => rtrim(config('app.url'), '/') . '/api/payments/payment-success?token=' . $payment->hash,
                'failureUrl'      => rtrim(config('app.url'), '/') . '/api/payments/payment-failed?token=' . $payment->hash,
                'notificationUrl' => rtrim(config('app.url'), '/') . '/api/payments/payment-process?token=' . $payment->hash,
            ];
            $payloadV1['signature'] = $this->makeSignature($payloadV1);

            $response = $this->request('/pos/webPayments', $payloadV1);
        }

        if (!$response) {
            error_log('Oplati: empty or invalid response when creating payment link');
            return false;
        }

        $meta = array_merge($response, ['orderPaymentId' => $payment->id]);
        $payment->meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $payment->save();

        if (!empty($response['paymentUrl'])) {
            return $response['paymentUrl'];
        }

        if (!empty($response['redirectUrl'])) {
            return $response['redirectUrl'];
        }

        if (!empty($response['order']['id'])) {
            return $this->settings['pay_base'] . '/' . $response['order']['id'];
        }

        if (!empty($response['paymentId'])) {
            return rtrim($this->settings['pay_base'], '/') . '/' . $response['paymentId'];
        }

        if (!empty($response['dynamicQR'])) {
            return false;
        }

        error_log('Oplati: payment link not found in response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        return false;
    }

    /**
     * Обработка callback-а от Oplati
     */
    public function handleCallback($paymentHash)
    {
        if (
            !isset($_SERVER['PHP_AUTH_USER']) ||
            !isset($_SERVER['PHP_AUTH_PW']) ||
            $_SERVER['PHP_AUTH_USER'] !== $this->settings['reg_num'] ||
            $_SERVER['PHP_AUTH_PW'] !== $this->settings['password']
        ) {
            error_log('Oplati callback unauthorized');
            return false;
        }

        $raw = file_get_contents('php://input');
        $response = json_decode($raw, true);

        if (!$response) {
            error_log("Oplati callback invalid JSON: " . $raw);
            return false;
        }

        if (!$this->verifySignature($response)) {
            error_log("Oplati callback invalid signature");
            return false;
        }

        if (
            isset($response['transaction']['type']) &&
            $response['transaction']['type'] === 'payment' &&
            $response['transaction']['status'] === 'successful'
        ) {
            try {
                $processor = $this->modx->commerce->loadProcessor();
                $payment   = $processor->loadPaymentByHash($paymentHash);

                if (!$payment) {
                    throw new Exception("Payment not found by hash {$paymentHash}");
                }

                $isProcessed = $processor->processPayment($payment['id'], $payment['amount']);
                $payment     = $processor->loadPayment($payment['id']);
                $order       = $processor->loadOrder($payment['order_id']);

                $fields = $order['fields'];
                $fields['online_payment_price'] = $payment['amount'];
                $fields['is_paid']              = true;

                \App\Models\CommerceOrders::where("id", $order['id'])->update([
                    'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                ]);

                return $isProcessed;
            } catch (Exception $e) {
                error_log("Oplati payment process failed: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Отправка curl-запроса
     */
    protected function request($uri, $data): bool|array
    {
        $url = rtrim($this->settings['base_url'], '/') . $uri;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->settings['timeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'regNum: ' . $this->settings['reg_num'],
            'password: ' . $this->settings['password'],
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);

        if ($response === false) {
            error_log("Oplati request failed: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            error_log("Oplati invalid response: " . $response);
            return false;
        }
        Log::info("Oplati request received " . $response);
        return $decoded;
    }

    /**
     * Подпись запроса
     */
    protected function makeSignature(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $json, $this->settings['secret_key']);
    }

    /**
     * Проверка подписи ответа
     */
    protected function verifySignature(array $response): bool
    {
        if (empty($response['signature'])) {
            return false;
        }

        $given = $response['signature'];
        $data  = $response;
        unset($data['signature']);

        $computed = $this->makeSignature($data);
        return hash_equals($computed, $given);
    }
}
