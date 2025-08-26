<?php

namespace App\Services\Payment;

use App\Services\Payment\Payment;

class Oplati extends Payment
{
    public $settings;

    public function __construct()
    {
        $this->settings = [
            'base_url'   => rtrim(env('OPLATI_BASE_URL', ''), '/'),
            'reg_num'    => env('OPLATI_REGNUM', 'OPL000063995'),
            'password'   => env('OPLATI_PASSWORD', 'AptekaOnline34'),
            'pay_base'   => rtrim(env('OPLATI_PAY_BASE', 'https://pay.o-plati.by/order'), '/'),
        ];
    }

    public function getPaymentLink($order='', $payment='')
    {
        $amountMinor = (int)round($payment->amount * 100);

        $payload = [
            'shift' => '1',
            'sum' => $amountMinor,
            'orderNumber' => (string)($order->id . '-' . $payment->id),
            'details' => [
                'title' => 'Оплата заказа #' . $order->id,
                'amountTotal' => $amountMinor,
                'items' => [
                    [
                        'type' => 1,
                        'name' => 'Заказ #' . $order->id,
                        'quantity' => 1,
                        'unit' => 'шт',
                        'price' => $amountMinor,
                        'cost' => $amountMinor,
                    ]
                ],
            ],
            'successUrl' => rtrim(config('app.url'), '/') . '/api/payments/payment-success?token=' . $payment->hash,
            'failureUrl' => rtrim(config('app.url'), '/') . '/api/payments/payment-failed?token=' . $payment->hash,
            'notificationUrl' => rtrim(config('app.url'), '/') . '/api/payments/payment-process?token=' . $payment->hash,
        ];

        $response = $this->request($payload);
        if (!$response) {
            return false;
        }

        $meta = array_merge($response, ['orderPaymentId' => $payment->id]);
        $payment->meta = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $payment->save();

        if (!empty($response['paymentUrl'])) {
            return $response['paymentUrl'];
        }
        if (!empty($response['order']) && !empty($response['order']['id'])) {
            if (!empty($this->settings['pay_base'])) {
                return $this->settings['pay_base'] . '/' . $response['order']['id'];
            }
        }

        return false;
    }

    public function handleCallback($paymentHash)
    {
        if (!isset($_SERVER['PHP_AUTH_USER'])
            || !isset($_SERVER['PHP_AUTH_PW'])
            || $_SERVER['PHP_AUTH_USER'] != $this->settings['reg_num']
            || $_SERVER['PHP_AUTH_PW'] != $this->settings['password']
        ) {
            $this->modx->logEvent(0, 3, 'Notify response can not be authorized', 'Commerce Bepaid Payment');

            return false;
        }

        $response = json_decode(file_get_contents('php://input'), true);

        if (isset($response['transaction']) && isset($response['transaction']['tracking_id'])
            && isset($response['transaction']['type']) && $response['transaction']['type'] == 'payment'
            && !empty($response['transaction']['status']) && $response['transaction']['status'] === "successful"
        ) {

            $processor = $this->modx->commerce->loadProcessor();

            try {
                $payment = $processor->loadPaymentByHash($paymentHash);

                if (!$payment) {
                    throw new Exception('Payment "' . htmlentities(print_r($paymentHash, true)) . '" . not found!');
                }

                $isProcessed = $processor->processPayment($payment['id'], $payment['amount']);
                $payment = $processor->loadPayment($payment['id']);
                $order = $processor->loadOrder($payment['order_id']);

                $fields = $order['fields'];

                $fields['online_payment_price'] = $payment['amount'];
                $fields['is_paid'] = true;

                CommerceOrders::where("id", $order['id'])->update([
                    'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                ]);

                return $isProcessed;
            } catch (Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Bepaid Payment');

                return false;
            }
        }

        return false;
    }

    protected function request($data)
    {
        if (empty($this->settings['base_url']) || empty($this->settings['reg_num']) || empty($this->settings['password'])) {
            return false;
        }

        $url = $this->settings['base_url'] . '/pos/webPayments/v2';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'regNum: ' . $this->settings['reg_num'],
            'password: ' . $this->settings['password'],
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return false;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : false;
    }
}
