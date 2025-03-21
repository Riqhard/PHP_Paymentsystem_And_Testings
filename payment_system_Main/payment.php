<?php

namespace PaymentProcessor;

class Payment
{
    private $state = false;
    private $status = null;
    private $orderUID = null;
    private $error = null;
    private $errorAdditional = null;
    private $paymentLink = null;
    private $paymentIdentifier = null;


    public function __construct(
        $state=false, 
        $status=null, 
        $orderUID=null, 
        $error=null, 
        $errorAdditional=null, 
        $paymentLink=null, 
        $paymentIdentifier=null
    )
    {
        $this->state = $state;
        $this->status = $status;
        $this->orderUID = $orderUID;
        $this->error = $error;
        $this->errorAdditional = $errorAdditional;
        $this->paymentLink = $paymentLink;
        $this->paymentIdentifier = $paymentIdentifier;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setOrderUID($orderUID)
    {
        $this->orderUID = $orderUID;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    public function setErrorAdditional($errorAdditional)
    {
        $this->errorAdditional = $errorAdditional;
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

    public function getStatus()
    {
        return $this->status;
    }

    public function getOrderUID()
    {
        return $this->orderUID;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getErrorAdditional()
    {
        return $this->errorAdditional;
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