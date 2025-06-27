<?php

namespace Commerce\Processors;

use Commerce\Carts\OrderCart;
use EvolutionCMS\Gateway\Traits\MultifieldsTrait;
use Exception;
use Illuminate\Support\Facades\View;
use EvolutionCMS\Gateway\Services\OrderService;

class OrdersProcessor implements \Commerce\Interfaces\Processor
{
    protected $modx;

    protected $cart;
    protected $order_id;
    protected $order = [];


    public function getOrder()
    {
        if (empty($this->order) && !empty($this->order_id)) {
            $this->loadOrder($this->order_id);
        }

        if (!empty($this->order)) {
            return $this->order;
        }

        return null;
    }

    public function loadOrder($order_id, $force = false)
    {
        if (!empty($this->order) && $this->order['id'] == $order_id && !$force) {
            return $this->order;
        }

        $query = $this->modx->db->select('*', $this->tableOrders, "`id` = '" . (int)$order_id . "'");

        if ($this->modx->db->getRecordCount($query)) {
            $this->order = $this->modx->db->getRow($query);
            $this->order['fields'] = json_decode($this->order['fields'], true);
            $this->order_id = $this->order['id'];
            $this->cart = null;

            return $this->order;
        }

        return null;
    }

    public function loadOrderByHash($order_hash)
    {
        $db = $this->modx->db;

        $query = $db->select('*', $this->tableOrders, "`hash` = '" . $db->escape((string)$order_hash) . "'");

        if ($db->getRecordCount($query)) {
            $this->order = $db->getRow($query);
            $this->order['fields'] = json_decode($this->order['fields'], true);
            $this->order_id = $this->order['id'];
            $this->cart = null;

            return $this->order;
        }

        return null;
    }

    /**
     * Оплата заказа по его хэшу.
     * Создается новый платеж и пользователь перенаправляется на оплату.
     *
     * @param string $hash Хэш заказа
     */
    public function payOrderByHash($hash)
    {
        
        $order = $db->getRow($db->select('*', $this->tableOrders, "`hash` = '" . $db->escape($hash) . "'"));

        if (!empty($order)) {
            $statusCanBePaid = ci()->statuses->canBePaid($order['status_id']);

            if (!$statusCanBePaid) {
                return false;
            }

            $amount = $this->getOrderPaymentsAmount($order['id']);

            // Если сумма предыдущих оплат больше суммы заказа, отменяем оплату
            if ($amount >= $order['amount'] || abs($amount - $order['amount']) < 0.01) {
                return false;
            }

            // Если оплат еще не было, проверяем,чтобы срок создания оплаты
            // был не больше указанного в настройках плагина
            if (!$amount) {
                $hours = 24 * $this->modx->commerce->getSetting('payment_wait_time', 3);
                $diff  = (new \DateTime())->diff(new \DateTime($order['created_at']));

                if ($diff->days * 24 + $diff->h > $hours) {
                    return false;
                }
            }

            $order = $this->loadOrder($order['id']);

            if (!empty($order['fields']['payment_method'])) {
                $payment = $this->modx->commerce->getPayment($order['fields']['payment_method']);

                $this->modx->invokeEvent('OnBeforePaymentProcess', [
                    'order'   => &$order,
                    'payment' => $payment,
                ]);

                ci()->flash->set('last_order_id', $order['id']);

                $redirect = $payment['processor']->createPaymentRedirect();

                if ($redirect) {
                    if (!empty($redirect['link'])) {
                        $this->modx->sendRedirect($redirect['link']);
                    } else {
                        echo $redirect['markup'];
                    }

                    return true;
                }
            }
        }

        return false;
    }

    protected function preparePayment($payment)
    {
        if (!empty($payment)) {
            $payment['meta'] = json_decode($payment['meta'], true);

            if (empty($payment['meta'])) {
                $payment['meta'] = [];
            }

            return $payment;
        }

        return null;
    }

    public function loadPayment($payment_id)
    {
        
        return $this->preparePayment($db->getRow($db->select('*', $this->tablePayments, "`id` = '" . intval($payment_id) . "'")));
    }

    public function loadPaymentByHash($hash)
    {
        
        return $this->preparePayment($db->getRow($db->select('*', $this->tablePayments, "`hash` = '" . $db->escape($hash) . "'")));
    }

    public function createPayment($order_id, $amount)
    {
        
        $hash = ci()->commerce->generateRandomString(16);

        $paid = $this->getOrderPaymentsAmount($order_id);
        $diff = number_format($amount - $paid, 2, '.', '');
        $meta = [];


        $order = $this->loadOrder($order_id);

        $payment = [
            'order_id'       => $order_id,
            'amount'         => $diff,
            'hash'           => $hash,
            'payment_method' => $order['fields']['payment_method'],
            'meta'           => $meta,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $payment['id'] = $this->savePayment($payment);
        return $payment;
    }

    public function savePayment($payment)
    {
        
        $values = [
            'order_id'       => $payment['order_id'],
            'amount'         => (float) $payment['amount'],
            'hash'           => $db->escape($payment['hash']),
            'payment_method' => $db->escape($payment['payment_method']),
            'meta'           => !empty($payment['meta']) ? $db->escape(json_encode($payment['meta'], JSON_UNESCAPED_UNICODE)) : '',
            'created_at'     => $payment['created_at'],
        ];

        $table = $this->modx->getFullTablename('commerce_order_payments');

        if (isset($payment['id'])) {
            $db->update($values, $table, "`id` = '" . intval($payment['id']) . "'");
        } else {
            $payment['id'] = $db->insert($values, $table);
        }

        return $payment['id'];
    }

    public function getOrderPaymentsAmount($order_id)
    {
        
        $payments = $db->select('*', $this->tablePayments, "`order_id` = '" . intval($order_id) . "' AND `paid` = 1");
        $amount = 0;

        while ($payment = $db->getRow($payments)) {
            $amount += (float)$payment['amount'];
        }

        return $amount;
    }

    public function processPayment($payment_id, $amount, $status = null)
    {
        
        $commerce = ci()->commerce;

        if (is_array($payment_id) && !empty($payment_id['id']) && !empty($payment_id['order_id'])) {
            $payment    = $payment_id;
            $payment_id = $payment['id'];
        } else {
            $payment = $this->loadPayment($payment_id);
        }

        if (empty($payment)) {
            throw new Exception('Payment ' . print_r($payment_id, true) . ' not found!');
        }

        if (!empty($payment['paid'])) {
            throw new Exception('Payment ' . print_r($payment_id, true) . ' already paid!');
        }

        $order_id = $payment['order_id'];
        $order = $this->loadOrder($order_id);

        if (is_null($order)) {
            throw new Exception('Order ' . print_r($order_id, true) . ' not found!');
        }

        $statusCanBePaid = ci()->statuses->canBePaid($order['status_id']);

        if (!$statusCanBePaid) {
            throw new Exception('Order ' . print_r($order_id, true) . ' cannot be paid by status restriction!');
        }

        $db->update(['paid' => 1], $this->tablePayments, "`id` = '" . intval($payment_id) . "'");
        $total = $this->getOrderPaymentsAmount($order_id);
        $fullyPaid = $total >= $order['amount'] || abs($total - $order['amount']) < 0.01;

        $tpl = ci()->tpl;

        if (!empty($order['lang'])) {
            $prevLangCode = $commerce->setLang($order['lang']);
        }

        $lang = $commerce->getUserLanguage('order');

        if ($fullyPaid) {
            $history = $lang['order.order_full_paid'];
        } else {
            $history = $lang['order.order_paid'];
        }

        $comment = $tpl->parseChunk($history, [
            'order'  => $order,
            'amount' => ci()->currency->format($amount),
        ]);

        if (!empty($prevLangCode)) {
            $commerce->setLang($prevLangCode);
        }

        if (is_null($status)) {
            $status = $commerce->getSetting('status_id_after_payment', 3);
        }

        $this->changeStatus($order_id, $status, $comment, true);

        $this->modx->invokeEvent('OnOrderPaid', [
            'order_id'   => $order_id,
            'order'      => $order,
            'status_id'  => $status,
            'payment'    => $payment,
            'total'      => $total,
            'fully_paid' => $fullyPaid,
        ]);

        $this->getCart();
        $template = $commerce->getSetting('order_paid', $commerce->getUserLanguageTemplate('order_paid'));

        $body = $tpl->parseChunk($template, [
            'payment' => $payment,
            'amount'  => $amount,
            'order'   => $order,
        ], true);

        $subject = $tpl->parseChunk($lang['order.subject_order_paid'], [
            'order' => $order,
        ], true);

        $mailer = new \Helpers\Mailer($this->modx, [
            'to'      => $commerce->getSetting('email', $this->modx->getConfig('emailsender')),
            'subject' => $subject,
        ]);

        $mailer->send($body);

        return true;
    }

    public function startOrder()
    {
        if (!$this->isOrderStarted()) {
            $_SESSION[$this->sessionKey] = [];
        }
    }

    public function isOrderStarted()
    {
        return isset($_SESSION[$this->sessionKey]);
    }

    public function updateRawData($data)
    {
        foreach (['formid', 'hashes'] as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }

        $_SESSION[$this->sessionKey] = $data;
        $this->modx->invokeEvent('OnOrderRawDataChanged', ['data' => $data]);
        $_SESSION[$this->sessionKey] = $data;
    }

    public function getRawData()
    {
        if (isset($_SESSION[$this->sessionKey])) {
            return $_SESSION[$this->sessionKey];
        }

        return [];
    }

    public function populateOrderPlaceholders($order_id)
    {
        $order = $this->loadOrder($order_id);

        if (!empty($order)) {
            $placeholder = ['commerce_order' => $order];
            $this->modx->setPlaceholder('commerce_order', $order);
            $this->modx->toPlaceholders($placeholder);
        }

        $this->modx->invokeEvent('OnOrderPlaceholdersPopulated', [
            'order' => &$order,
        ]);
    }

    public function populatePaymentPlaceholders($payment_id)
    {
        $payment = $this->loadPayment($payment_id);

        if (!empty($payment) && $payment['paid'] == 1) {
            $placeholder = ['commerce_payment' => $payment];
            $this->modx->setPlaceholder('commerce_payment', $payment);
            $this->modx->toPlaceholders($placeholder);
        }
    }

    public function populateOrderPaymentLink($tpl = null)
    {
        $order = $this->getOrder();

        if ($order) {
            if ($tpl == null) {
                $lang = $this->modx->commerce->getUserLanguage('order');
                $tpl = $lang['order.order_payment_link'];
            }

            return ci()->tpl->parseChunk($tpl, [
                'order' => $order,
                'link'  => $this->modx->getConfig('site_url') . 'commerce/payorder?hash=' . $order['hash'],
            ], true);
        }

        return '';
    }

    public function getCurrentDelivery()
    {
        if (isset($_SESSION[$this->sessionKey]['delivery_method'])) {
            return $_SESSION[$this->sessionKey]['delivery_method'];
        }

        $order = $this->getOrder();

        if (isset($order['fields']['delivery_method'])) {
            return $order['fields']['delivery_method'];
        }

        $code = $this->modx->commerce->getSetting('default_delivery');

        if (!empty($code)) {
            return $code;
        }

        $deliveries = $this->modx->commerce->getDeliveries();

        if (count($deliveries)) {
            return key($deliveries);
        }

        return null;
    }

    public function setCurrentDelivery($delivery)
    {
        $this->startOrder();
        $old = $_SESSION[$this->sessionKey]['delivery_method'] ?? null;
        $_SESSION[$this->sessionKey]['delivery_method'] = $delivery;
        return $old;
    }

    public function getCurrentPayment()
    {
        if (isset($_SESSION[$this->sessionKey]['payment_method'])) {
            return $_SESSION[$this->sessionKey]['payment_method'];
        }

        $order = $this->getOrder();

        if (isset($order['fields']['payment_method'])) {
            return $order['fields']['payment_method'];
        }

        $code = $this->modx->commerce->getSetting('default_payment');

        if (!empty($code)) {
            return $code;
        }

        $payments = $this->modx->commerce->getPayments();

        if (count($payments)) {
            return key($payments);
        }

        return null;
    }

    public function setCurrentPayment($payment)
    {
        $this->startOrder();
        $old = $_SESSION[$this->sessionKey]['payment_method'] ?? null;
        $_SESSION[$this->sessionKey]['payment_method'] = $payment;
        return $old;
    }

    private function normalizePrice($price)
    {
        $price = str_replace(',', '.', $price);
        return number_format((float)$price, 6, '.', '');
    }

    public function getPaymentLink(string $hash)
    {
        return $this->modx->getConfig('site_url') . 'commerce/payorder?hash=' . $hash;
    }
}
