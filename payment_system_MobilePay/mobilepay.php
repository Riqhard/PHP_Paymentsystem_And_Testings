<?php
namespace MobilePay;

require_once 'mobilepay_definitions.php';
require_once 'response.php';

class MobilePayProcessor 
{
    private $merchantID;
    private $subscriptionKey;
    private $returnURL;

    public function __construct($merchantID, $subscriptionKey, $returnURL)
    {
        $this->merchantID = $merchantID;
        $this->subscriptionKey = $subscriptionKey;
        $this->returnURL = $returnURL;
    }


    // Curl request to MobilePay API.
    // Based on PHP documentation and https://php.watch/articles/php-curl-security-hardening

    private function sendRequest($request, $headers, $endpoint, $method) 
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, MOBILEPAY_API_URL . $endpoint);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request); // String
        } elseif ($method != 'GET') {
            throw new \Exception('Invalid method, only GET and POST allowed');
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // Check

        // Harden the curl request
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, 0); // Test if mobilepay supports this feature with 'true'
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }


    private function createHeader($type, $idempotencyKey = null, $referenceID = null) 
    {
        $headers = array(
            'Content-Type: application/json',
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
            'Merchant-serial-number: ' . $this->merchantID
            );
        switch ($type) {
            case MOBILEPAY_CREATE:
                if ($idempotencyKey == null) {
                    throw new \Exception('Idempotency key required for create request');
                }
                $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
                break;
            case MOBILEPAY_QUERY:
                if ($referenceID == null) {
                    throw new \Exception('Reference ID required for query request');
                }
                $headers[] = 'Reference-Id: ' . $referenceID;
                break;
            case MOBILEPAY_CANCEL:
                if ($referenceID == null) {
                    throw new \Exception('Reference ID required for cancel request');
                }
                $headers[] = 'Reference-Id: ' . $referenceID;
                break;
            case MOBILEPAY_ADJUST:
                if ($referenceID == null || $idempotencyKey == null) {
                    throw new \Exception('Reference ID and idempotency key required for adjust request');
                }
                $headers[] = 'Reference-Id: ' . $referenceID;
                $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
                break;
            default:
                throw new \Exception('Invalid request type');
        }
    }


    // Create a unique idempotency key for the requests
    // How *exactly* this is used in the requests is not clear from the documentation.
    // Could be that it is used on all related requests or each request needs a new one.
    // 
    // Currently just creates uniqid. This might need to be changed to something better, 
    // but it should work for now. (The spec does not specify what the idempotency key should be)
    
    public function createIdempotencyKey() 
    {
        return uniqid();
    }


    // Required data: Amount*, ReferenceID*, IdempotencyKey*, Description+. Always the same: (PaymentMethod, returnUrl, userFlow)
    
    public function createPayment($idempotencyKey, $referenceID, $token, $amount, $description = null) 
    {
        $response = new Response(false);
        $request = array(
            'amount' => array(
                'currency' => MOBILEPAY_DEFAULT_CURRENCY,
                'total' => $amount * 100
            ),
            'paymentMethod' => MOBILEPAY_PAYMENT_METHOD,
            'returnUrl' => $this->returnURL . "/" . $token,
            'userFlow' => MOBILEPAY_USERFLOW
        );
        if ($description != null) {
            $request['description'] = $description;
        }
        $request = json_encode($request);
        $headers = $this->createHeader(MOBILEPAY_CREATE, $idempotencyKey, $referenceID);
        $data = json_decode($this->sendRequest(request: $request, headers: $headers, endpoint: "", method: 'POST'));
        
        // TODO: This should check the HTTP response codes and do stuff based on that
        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
        return $response;
    }



    public function queryPayment($referenceID) 
    {
        $response = new Response(false);
        $headers = $this->createHeader(MOBILEPAY_QUERY, referenceID: $referenceID);
        $endpoint = "/" . $referenceID;
        $data = json_decode($this->sendRequest(
            request: null, 
            headers: $headers, 
            endpoint: $endpoint, 
            method: 'GET'
        ));

        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
        return $response;
    }


    public function cancelPayment($referenceID) 
    {
        $response = new Response(false);
        $headers = $this->createHeader(MOBILEPAY_CANCEL, referenceID: $referenceID);
        $endpoint = "/" . $referenceID . "/cancel";
        $data = json_decode($this->sendRequest(
            request: null, 
            headers: $headers, 
            endpoint: $endpoint, 
            method: 'POST'
        ));

        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
        return $response;
    }


    // Capture specific amount of authorized payment.

    public function capturePayment($referenceID, $idempotencyKey, $amount) 
    {
        $response = new Response(false);
        $headers = $this->createHeader(MOBILEPAY_ADJUST, referenceID: $referenceID, idempotencyKey: $idempotencyKey);
        $endpoint = "/" . $referenceID . "/capture";
        $request = array(
            'amount' => $amount,
            'currency' => MOBILEPAY_DEFAULT_CURRENCY
        );

        $request = json_encode($request);
        $data = json_decode($this->sendRequest(
            request: $request, 
            headers: $headers, 
            endpoint: $endpoint, 
            method: 'POST'
        ));

        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
        return $response;
    }
    
    
    public function refundPayment($referenceID, $idempotencyKey, $amount) 
    {
        $response = new Response(false);
        $headers = $this->createHeader(MOBILEPAY_ADJUST, referenceID: $referenceID, idempotencyKey: $idempotencyKey);
        $endpoint = "/" . $referenceID . "/refund";
        $request = array(
            'amount' => $amount,
            'currency' => MOBILEPAY_DEFAULT_CURRENCY
        );

        $request = json_encode($request);
        $data = json_decode($this->sendRequest(
            request: $request, 
            headers: $headers, 
            endpoint: $endpoint, 
            method: 'POST'
        ));

        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
        return $response;
    }


    // Testing function to force approve a payment. 
    public function forceApprove($referenceID)
    {
        $response = new Response(false);
        $headers = $this->createHeader(MOBILEPAY_FORCE_APPROVE, referenceID: $referenceID);
        $endpoint = "/" . $referenceID . "/approve";
        $data = json_decode($this->sendRequest(
            request: null, 
            headers: $headers, 
            endpoint: $endpoint, 
            method: 'POST'
        ));
        if ($data == null) {
            $response->setError('Invalid response from MobilePay');
        } else {
            $response->setResponse($data);
            $response->setStatus(true);
        }
    }

    // Convinience functions for the user.

    public function isPaymentAuthorized($response) 
    {
        if ($response->getStatus() == false) {
            return false;
        }
        return isset($response->getResponse()->state) && $response->getResponse()->state == MOBILEPAY_STATE_AUTHORIZED;
    }
    

    public function getPaymentState($response) 
    {
        if ($response->getStatus() == false) {
            return null;
        }
        if (!isset($response->getResponse()->state)) {
            return null;
        }
        return $response->getResponse()->state;
    }
}




?>