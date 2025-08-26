<?php

namespace App\Services\Payment;

class EripExpresspay extends Payment
{
    protected $settings;

    public function __construct()
    {
        $this->settings = [
            'service_id' => env('EXPRESSPAY_SERVICE_ID', ''),
            'token'      => '0afdc94f67f643debcdd7348992df857',
            'secret'     => env('EXPRESSPAY_SECRET', ''),
            'public_key' => env('EXPRESSPAY_PUBLIC_KEY', ''),
            'basic_secret' => env('EXPRESSPAY_BASIC_SECRET', ''),
            'test'       => env('EXPRESSPAY_TEST', false),
        ];
    }

    public function getPaymentLink($order = '', $payment = '')
    {
        // Amount with dot decimal for invoices API
        $amountByn = (float)number_format((float)$payment->amount, 2, '.', '');

        $fio = explode(' ', trim((string)($order->name ?? '')));
        $surname = $fio[0] ?? '';
        $firstname = $fio[1] ?? '';

        $request = [
            'AccountNo'         => (string)$order->id,
            'Amount'            => $amountByn,
            'Currency'          => 933,
            'Expiration'        => '',
            'Info'              => 'Оплата заказа #' . $order->id,
            'Surname'           => $surname,
            'FirstName'         => $firstname,
            'City'              => '',
            'Street'            => '',
            'House'             => '',
            'Building'          => '',
            'Apartment'         => '',
            'IsNameEditable'    => 0,
            'IsAddressEditable' => 0,
            'IsAmountEditable'  => 0,
            'EmailNotification' => filter_var($order->email, FILTER_VALIDATE_EMAIL) ? $order->email : '',
            'SmsPhone'          => isset($order->phone) ? substr(preg_replace('/[^\d]+/', '', (string)$order->phone), 0, 15) : '',
            'ReturnInvoiceUrl'  => 1,
        ];

        $response = $this->sendInvoicesRequest($request);
        if ($response === false) {
            return false;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return false;
        }

        $data = array_merge($data, ['orderPaymentId' => $payment->id]);
        $payment->meta = json_encode($data, JSON_UNESCAPED_UNICODE);
        $payment->save();

        if (!empty($data['FormUrl'])) {
            return $data['FormUrl'];
        }
        if (!empty($data['InvoiceNo'])) {
            $overrideBase = rtrim((string)env('EXPRESSPAY_PAY_BASE', ''), '/');
            if ($overrideBase !== '') {
                return $overrideBase . '/' . $data['InvoiceNo'];
            }
            $base = $this->settings['test'] ? 'https://sandbox-pay.express-pay.by/order/' : 'https://pay.express-pay.by/order/';
            return $base . $data['InvoiceNo'];
        }

        return false;
    }

    protected function sendRequestPOST(array $params): bool|string
    {
        $base = $this->settings['test'] ? 'https://sandbox-api.express-pay.by' : 'https://api.express-pay.by';
        $url = $base . '/v1/web_invoices';

        $params['Token'] = $this->settings['token'];
        $body = http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        // In case TLS issues in some envs, can add: CURLOPT_SSL_VERIFYPEER true
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    // Use /v1/invoices with token in query and Authorization Basic
    protected function sendInvoicesRequest(array $payload): bool|string
    {
        $base = $this->settings['test'] ? 'https://sandbox-api.express-pay.by' : 'https://api.express-pay.by';
        $url = $base . '/v1/invoices?token=' . urlencode($this->settings['token']);

        $headers = [
            'Content-Type: application/json',
        ];
        $basicUser = $this->settings['public_key'];
        $basicPass = $this->settings['basic_secret'] ?: $this->settings['secret'];
        if (!empty($basicUser) && !empty($basicPass)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($basicUser . ':' . $basicPass);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    protected function computeSignatureForInvoice(array $requestParams, string $secretWord): string
    {
        $secretWord = trim($secretWord);
        $normalized = array_change_key_case($requestParams, CASE_LOWER);
        $sequence = [
            'serviceid', 'accountno', 'amount', 'currency', 'expiration', 'info', 'surname', 'firstname',
            'patronymic', 'city', 'street', 'house', 'building', 'apartment', 'isnameeditable', 'isaddresseditable',
            'isamounteditable', 'emailnotification', 'smsphone'
        ];
        $result = '';
        foreach ($sequence as $key) {
            $result .= $normalized[$key] ?? '';
        }
        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord));
        return $hash;
    }
}
