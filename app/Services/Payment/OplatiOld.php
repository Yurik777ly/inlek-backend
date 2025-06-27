<?php

namespace Commerce\Payments;

use EvolutionCMS\Gateway\Models\CommerceOrderProducts;
use EvolutionCMS\Gateway\Models\CommerceOrdersPayments;

class Oplati extends Payment
{
    /**
     * @var bool
     */
    protected bool $debug = false;

    /**
     * @var bool
     */
    protected bool $test = false;

    /**
     * @var string
     */
    private string $shopId = "";

    /**
     * @var string
     */
    private string $secretKey = "";

    /**
     * @var int
     */
    private int $redirectId;

    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);

        $this->lang = $modx->commerce->getUserLanguage('oplati');
        $this->debug = $this->getSetting('debug') == '1';        
        $this->test = $this->getSetting('test') == '1';
        $this->shopId = $this->getSetting('shop_id');
        $this->secretKey = $this->getSetting('secret_key');
        $this->redirectId = (int) $this->getSetting('redirect_page_id');
    }

    public function getPaymentLink()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $currency  = ci()->currency->getCurrency($order['currency']);
        $payment   = $this->createPayment($order['id'], $order['amount']);

        $headers = [
            "regNum: {$this->shopId}",
            "password: {$this->secretKey}",
            "Content-Type: application/json"
        ];

        $order_id = $order['id'];
        $order_hash = $order['hash'];
        $products = CommerceOrderProducts::where('order_id', $order_id)
            ->get()
            ->toArray();

        if(!empty($products)) {
            foreach($products as $item_key => $item) {
                $products[$item_key] = [
                    'type' => 1,
                    'name' => $item['title'],
                    'quantity' => $item['count'],
                    'unit' => 'шт.',
                    'price' => $item['price'],
                    'cost' => $item['price']
                ];
            }
        }

        $site_url = MODX_SITE_URL;
        $successUrl = "{$site_url}commerce/bepaid/payment-success";
        $failureUrl = "{$site_url}commerce/bepaid/payment-failed";

        $order_amount = (float) $order['amount'];

        $data = [
            'sum' => $order_amount,
            'orderNumber' => $order_id,
            'details' => [
                'items' => $products, JSON_UNESCAPED_UNICODE,
                'amountTotal' => $order_amount,
                'title' => "Заказ {$order_id}"
            ],
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl,
        ];

        if ($response = $this->request($data, $headers)) {
            $response = array_merge($response, ['orderPaymentId' => $payment['id']]);
            CommerceOrdersPayments::where("id", $payment['id'])->update([
                'meta' => json_encode($response, JSON_UNESCAPED_UNICODE),
            ]);

            if (!empty($response['dynamicQR']) && !empty($response['paymentId'])) {
                if (empty($this->redirectId)) {
                    return false;
                }

                $response = json_encode($response, JSON_UNESCAPED_UNICODE);
                $url = \UrlProcessor::makeUrl($this->redirectId);
                return "{$url}?hash={$order_hash}&response={$response}";
            } elseif ($this->debug) {
                $this->modx->logEvent(0, 3, 'Request failed: <pre>' . print_r($data, true) . '</pre><pre>'. print_r($response, true) . '</pre>',
                    'Commerce Oplati Payment');
            }
        }

        return false;
    }

    public function handleCallback()
    {
        evo()->logEvent(0,1,json_encode($_SERVER, $_REQUEST), __CLASS__);
        dd(__CLASS__, __METHOD__, $_SERVER, $_REQUEST);
//        if (!isset($_SERVER['PHP_AUTH_USER'])
//            || !isset($_SERVER['PHP_AUTH_PW'])
//            || $_SERVER['PHP_AUTH_USER'] != $this->getSetting('shop_id')
//            || $_SERVER['PHP_AUTH_PW'] != $this->getSetting('secret_key')
//        ) {
//            $this->modx->logEvent(0, 3, 'Notify response can not be authorized', 'Commerce Oplati Payment');
//
//            return false;
//        }
//
//        $response = json_decode(file_get_contents('php://input'), true);
//
//        if (isset($response['transaction']) && isset($response['transaction']['tracking_id'])
//            && isset($response['transaction']['type']) && $response['transaction']['type'] == 'payment'
//            && !empty($response['transaction']['status']) && $response['transaction']['status'] === "successful"
//        ) {
//            $paymentHash = $this->getRequestPaymentHash();
//            $processor = $this->modx->commerce->loadProcessor();
//            try {
//                $payment = $processor->loadPaymentByHash($paymentHash);
//
//                if (!$payment) {
//                    throw new Exception('Payment "' . htmlentities(print_r($paymentHash, true)) . '" . not found!');
//                }
//
//                return $processor->processPayment($payment['id'], $payment['amount']);
//            } catch (Exception $e) {
//                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Oplati Payment');
//
//                return false;
//            }
//        }
//
//        return false;
    }

    protected function request(array $data, array $headers = [])
    {
        if ($this->test) {
            $url = "https://bpay-testcashdesk.lwo.by/ms-pay/pos/webPayments";
        } else {
            $url = "https://cashboxapi.o-plati.by/ms-pay/pos/webPayments";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            if ($this->debug) {
                $this->modx->logEvent(0, 3, 'Request failed: <pre>' . print_r($data, true) . '</pre><pre>'. print_r($error, true) . '</pre>',
                    'Commerce Oplati Payment');
            }

            return false;
        }

        return json_decode($response, true);
    }

    public function getRequestPaymentHash()
    {
        if (!empty($_REQUEST['paymentHash']) && is_scalar($_REQUEST['paymentHash'])) {
            return $_REQUEST['paymentHash'];
        }

        return null;
    }
}