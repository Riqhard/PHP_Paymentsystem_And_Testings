<?php
namespace PaymentProcessor;

class Payment
{
    private $state = false;
    private $orderUID = null;
    private $error = null;
    private $paymentLink = null;
    private $paymentIdentifier = null;

    public function __construct($state=false, $orderUID=null, $error=null, $paymentLink=null, $paymentIdentifier=null)
    {
        $this->state = $state;
        $this->orderUID = $orderUID;
        $this->error = $error;
        $this->paymentLink = $paymentLink;
        $this->paymentIdentifier = $paymentIdentifier;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function setOrderUID($orderUID)
    {
        $this->orderUID = $orderUID;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    public function setPaymentLink($paymentLink)
    {
        $this->paymentLink = $paymentLink;
    }

    public function setPaymentIdentifier($paymentIdentifier)
    {
        $this->paymentIdentifier = $paymentIdentifier;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getOrderUID()
    {
        return $this->orderUID;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getPaymentLink()
    {
        return $this->paymentLink;
    }

    public function getPaymentIdentifier()
    {
        return $this->paymentIdentifier;
    }
}
?>