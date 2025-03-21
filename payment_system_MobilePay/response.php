<?php
namespace MobilePay;

class Response 
{
    private $response = null;
    private $error = null;
    private $status = false;
    
    public function __construct($status, $response=null, $error=null) {
        $this->status = $status;
        $this->error = $error;
        $this->response = $response;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function setError($error) {
        $this->error = $error;
    }

    public function setResponse($response) {
        $this->response = $response;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getError() {
        return $this->error;
    }

    public function getResponse() {
        return $this->response;
    }
}

?>