<?php                               

namespace App\Services\Payment;

class Payment
{
    protected $payment_id = null;
    protected $settings = null;

    public function __construct(array $params = [])
    {
        $this->settings = $params;
    }

    public function init()
    {
        return false;
    }

    public function getPaymentLink($order='', $payment='')
    {
        return false;
    }

    public function getPaymentMarkup()
    {
        return '';
    }

    public function handleCallback($paymentHash)
    {
        return false;
    }

    public function handleSuccess()
    {
        return true;
    }

    public function handleError()
    {
        return true;
    }

    public function createPayment($order_id, $amount)
    {
        return ci()->commerce->loadProcessor()->createPayment($order_id, $amount);
    }

    public function createPaymentRedirect()
    {
        $link = $this->getPaymentLink();

        if (!empty($link)) {
            return ['link' => $link];
        }

        $markup = $this->getPaymentMarkup();

        if (!empty($markup)) {
            return ['markup' => $markup];
        }

        return false;
    }

    public function getRequestPaymentHash()
    {
        return null;
    }

}
