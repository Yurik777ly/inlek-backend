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
            // Тестовые ключи по документации: для web_invoices требуется подпись (ServiceId=4)
            'service_id' => 4,
            'token'      => 'a75b74cbcfe446509e8ee874f421bd66',
            'secret'     => 'sandbox.expresspay.by',
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

        // Для ERIP у ExpressPay часто требуется формат суммы с запятой и дополнительные поля возврата
        $amountByn = number_format((float)$payment->amount, 2, ',', '');

        $fio = explode(' ', trim((string)($order->name ?? '')));
        $surname = $fio[0] ?? '';
        $firstname = $fio[1] ?? '';

        // 1) Пробуем web_invoices (возвращает FormUrl — страница оплаты)
        $webParams = [
            'Token'             => $this->settings['token'],
            'ServiceId'         => $this->settings['service_id'],
            'AccountNo'         => (string)$order->id,
            'Amount'            => $amountByn,
            'Currency'          => 933,
            'ReturnType'        => 'json',
            'ReturnUrl'         => rtrim(config('app.url'), '/') . '/api/payments/payment-success?token=' . $payment->hash,
            'FailUrl'           => rtrim(config('app.url'), '/') . '/api/payments/payment-failed?token=' . $payment->hash,
            'Expiration'        => '',
            'Info'              => 'Оплата заказа #' . $order->id,
            'Surname'           => $surname,
            'FirstName'         => $firstname,
            'Patronymic'        => '',
            'City'              => '',
            'Street'            => '',
            'House'             => '',
            'Building'          => '',
            'Apartment'         => '',
            'IsNameEditable'    => 0,
            'IsAddressEditable' => 0,
            'IsAmountEditable'  => 0,
            'EmailNotification' => filter_var($order->email, FILTER_VALIDATE_EMAIL) ? $order->email : '',
            'SmsPhone'          => isset($order->phone) ? substr(preg_replace('/[^\d]+/', '', $order->phone), 0, 15) : '',
            'ReturnInvoiceUrl'  => 1,
        ];
        $webResponse = $this->sendWebInvoicesRequest($webParams);
        Log::info('EripExpresspay web_invoices response: ' . json_encode($webResponse, JSON_UNESCAPED_UNICODE));

        if (is_array($webResponse) && empty($webResponse['Errors'])) {
            $payment->meta = json_encode(array_merge($webResponse, ['orderPaymentId' => $payment->id]), JSON_UNESCAPED_UNICODE);
            $payment->save();

            // Для web_invoices ожидаем InvoiceUrl (публичная ссылка) либо InvoiceNo
            if (!empty($webResponse['InvoiceUrl'])) {
                return $webResponse['InvoiceUrl'];
            }
            if (!empty($webResponse['InvoiceNo'])) {
                return rtrim($this->settings['pay_base'], '/') . '/' . $webResponse['InvoiceNo'];
            }
        }

        // 2) Фолбэк: обычные invoices (вернёт InvoiceUrl или InvoiceNo)
        $payload = [
            'ServiceId'         => $this->settings['service_id'],
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
            'ReturnType'        => 'json',
            'ReturnUrl'         => rtrim(config('app.url'), '/') . '/api/payments/payment-success?token=' . $payment->hash,
            'FailUrl'           => rtrim(config('app.url'), '/') . '/api/payments/payment-failed?token=' . $payment->hash,
        ];

        $response = $this->sendInvoicesRequest($payload);

        Log::info('EripExpresspay request response: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

        if (!is_array($response) || !empty($response['Errors'])) {
            Log::error('EripExpresspay: payment link not found in response', $response);
            return null;
        }

        $payment->meta = json_encode(array_merge($response, ['orderPaymentId' => $payment->id]), JSON_UNESCAPED_UNICODE);
        $payment->save();

        // Предпочитаем публичную форму оплаты
        if (!empty($response['FormUrl'])) {
            return $response['FormUrl'];
        }

        if (!empty($response['InvoiceNo'])) {
            return rtrim($this->settings['pay_base'], '/') . '/' . $response['InvoiceNo'];
        }

        // В крайнем случае возвращаем InvoiceUrl (может вести в личный кабинет и требовать авторизацию)
        if (!empty($response['InvoiceUrl'])) {
            return $response['InvoiceUrl'];
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

        // Запрос оформляем к invoices (JSON) с token в query — провайдер может вернуть InvoiceUrl
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
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error('EripExpresspay curl error (invoices): ' . $err);
        }
        Log::info('EripExpresspay request response: ' . $response);

        return json_decode($response, true);
    }

    /**
     * Отправка запроса к /v1/web_invoices (формы оплаты)
     */
    protected function sendWebInvoicesRequest(array $params)
    {
        $base = $this->settings['test']
            ? 'https://sandbox-api.express-pay.by'
            : 'https://api.express-pay.by';

        // Подписываем параметры по требованиям web_invoices (обязательна подпись)
        $signature = $this->computeSignatureWeb($params, $this->settings['secret']);
        $params['Signature'] = $signature;

        // В теле запроса Token передавать не требуется — удаляем после формирования подписи
        unset($params['Token']);

        $url = $base . '/v1/web_invoices';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error('EripExpresspay curl error (web_invoices): ' . $err);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Вычисление подписи для web_invoices (совместимо с ExpresspayEripPayment)
     */
    protected function computeSignatureWeb(array $request_params, string $secret_word): string
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        // Порядок полей для add-web-invoice согласно документации
        $mapping = [
            'token','serviceid','accountno','amount','currency','expiration','info','surname','firstname','patronymic','city','street','house','building','apartment','isnameeditable','isaddresseditable','isamounteditable','emailnotification','smsphone','returntype','returnurl','failurl','returninvoiceurl'
        ];

        $result = '';
        foreach ($mapping as $item) {
            $result .= $normalized_params[$item] ?? '';
        }

        return strtoupper(hash_hmac('sha1', $result, $secret_word));
    }
}
