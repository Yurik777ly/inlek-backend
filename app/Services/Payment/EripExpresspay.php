<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

class EripExpresspay extends Payment
{
    protected $settings;

    public function __construct()
    {
        parent::__construct();

        $this->settings = [
            'service_id' => 2,
            'token'      => 'a75b74cbcfe446509e8ee874f421bd64',
            'secret'     => '',
            'test'       => true,
            'pay_base'   => 'https://sandbox-pay.express-pay.by/order',
        ];
    }

    /**
     * Получение ссылки для оплаты
     */
    public function getPaymentLink($order = null, $payment = null)
    {
        if (!$order || !$payment) {
            return null;
        }

        $amountByn = number_format((float)$payment->amount, 2, '.', '');

        $fio = explode(' ', trim((string)($order->name ?? '')));
        $surname = $fio[0] ?? '';
        $firstname = $fio[1] ?? '';

        $payload = [
            'AccountNo'         => (string)$order->id,
            'Amount'            => $amountByn,
            'Currency'          => 933, // BYN
            'Info'              => 'Оплата заказа #' . $order->id,
            'Surname'           => $surname,
            'FirstName'         => $firstname,
            'IsNameEditable'    => 0,
            'IsAddressEditable' => 0,
            'IsAmountEditable'  => 0,
            'EmailNotification' => filter_var($order->email, FILTER_VALIDATE_EMAIL) ? $order->email : '',
            'SmsPhone'          => isset($order->phone) ? substr(preg_replace('/[^\d]+/', '', $order->phone), 0, 15) : '',
            'ReturnInvoiceUrl'  => 1,
        ];

        $response = $this->sendInvoicesRequest($payload);

        Log::info('EripExpresspay request response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

        if (!is_array($response) || !empty($response['Errors'])) {
            Log::error('EripExpresspay: payment link not found in response', $response);
            return null;
        }

        $payment->meta = json_encode(array_merge($response, ['orderPaymentId' => $payment->id]), JSON_UNESCAPED_UNICODE);
        $payment->save();

        if (!empty($response['FormUrl'])) {
            return $response['FormUrl'];
        }

        if (!empty($response['InvoiceNo'])) {
            return rtrim($this->settings['pay_base'], '/') . '/' . $response['InvoiceNo'];
        }

        return null;
    }

    /**
     * Отправка запроса к /v1/invoices
     */
    protected function sendInvoicesRequest(array $payload)
    {
        $base = $this->settings['test']
            ? 'https://sandbox-api.express-pay.by'
            : 'https://api.express-pay.by';

        $url = $base . '/v1/invoices?token=' . urlencode($this->settings['token']);

        $headers = [
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        Log::info('EripExpresspay request response: ' . $response);

        return json_decode($response, true);
    }
}
