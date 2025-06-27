<?php

namespace App\Services\Payment;

use App\Services\Payment\Payment;

class Bepaid extends Payment
{
    public $settings;

    public function __construct()
    {
        $this->settings = [
            'test' => 1,
            'shop_id' => '27288',
            'secret_key' => '2b1554a3d2b476bb14bb31386c301dafe1f0d67a247825683bab0560d09c0aea',
        ];
    }

    public function getPaymentLink($order='', $payment='')
    {
        $payment->amount = round($payment->amount * 100, 2);
        $payment->amount = (int) $payment->amount;

        $customer = [
            'email' => $order->email,
            'phone' => $order->phone,
        ];

        $data = [
            'checkout' => [
                'transaction_type' => 'payment',
                'test' => $this->settings['test'] == '1',
                'settings' => [
                    'return_url'  => config('app.url') . '/api/payments/payment-success',
                    'success_url' => config('app.url') . '/api/payments/payment-success',

                    'decline_url' => config('app.url') . '/api/payments/payment-failed',
                    'fail_url'    => config('app.url') . '/api/payments/payment-failed',
                    'cancel_url'  => config('app.url') . '/api/payments/payment-failed',

                    'notification_url' => config('app.url') . '/payments/payment-process?paymentHash=' . $payment['hash'],
                    'language' => "ru",
                ],
                'order' => [
                    'currency' => 'BYN',
                    'amount' => $payment->amount,
                    'description' => 'Оплата заказа ' . $order->id,
                    'tracking_id' => $order->id . '-' . $payment->hash
                ],
                'customer' => $customer
            ]
        ];

        if ($response = $this->request($data)) {
            $response = array_merge($response, ['orderPaymentId' => $payment->id]);
            //update payment
            $payment->meta = json_encode($response, JSON_UNESCAPED_UNICODE);
            $payment->save();

            if (isset($response['checkout']['redirect_url'])) {
                return $response['checkout']['redirect_url'];
            }
        }

        return false;
    }

    public function handleCallback($paymentHash)
    {
        if (!isset($_SERVER['PHP_AUTH_USER'])
            || !isset($_SERVER['PHP_AUTH_PW'])
            || $_SERVER['PHP_AUTH_USER'] != $this->settings['shop_id']
            || $_SERVER['PHP_AUTH_PW'] != $this->settings['secret_key']
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
        $ch = curl_init('https://checkout.bepaid.by/ctp/api/checkouts');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Cache-Control: no-cache',
            'X-API-Version: 2'
        ]);

        curl_setopt($ch, CURLOPT_USERPWD, $this->settings['shop_id'] . ':' . $this->settings['secret_key']
        );

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true);
    }
}