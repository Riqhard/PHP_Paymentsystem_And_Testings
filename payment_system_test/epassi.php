<?php

# Constants and loading configuration from environment variables

# API payment rejection errors
define("ERROR_INVALID_REQ", "INVALID_REQUEST");
define("ERROR_INVALID_SIGNATURE", "INVALID_REQUEST_SIGNATURE");
define("ERROR_SITE_ERR", "SITE_DOES_NOT_EXIST");

# Some default texts used

define("ERROR_INVALID_REQ_TEXT", "Rejected: Malformed payment form");
define("ERROR_INVALID_SIGNATURE_TEXT", "MAC signature is invalid");
define("ERROR_SITE_ERR_TEXT", "Provided site login is incorrect");
define("PAY_BUTTON_TEXT", "Pay with Epassi");



##### Epassi functions. ######
# In general, if these functions return a boolean as the first element of the array, it indicates if the operation was successful or not.
# Rest of the return values are the results of the operation, if any.

class EpassiGenerator 
{
    private $mac;
    private $site;
    private $returnUrl;
    private $cancelUrl;
    private $rejectUrl;
    private $epassiApiUrl;

    public function __construct(
        $epassiLogin,
        $epassiKey,  
        $returnUrl, 
        $cancelUrl, 
        $rejectUrl, 
        $epassiApiUrl,
        $testing = false
    ) 
    {
        $this->mac = $epassiKey;
        $this->site = $epassiLogin;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->rejectUrl = $rejectUrl;
        $this->epassiApiUrl = $epassiApiUrl;   
        if ($testing) {
            $this->mac = "";
            $this->site = "";
        }
    }


    # Functions to verify the number and vat formats. Returns true or false depending on if the format is correct
    private function verifyNumberFormat($numb)
    {
        return preg_match('/^\d+\.\d{2}?$/', $numb);
    }

    private function verifyVatFormat($vat)
    {
        return preg_match('/^\d+\.\d{1}?$/', $vat);
    }



    # sha512 generator, returns array [bool, hash].
    private function generateSHA512($stamp, $site, $amount, $fee = "", $vatValue = "") 
    {
        
        if (!$this->verifyNumberFormat($amount)) {
            return [false, null];
        }
        if (!empty($fee) && !empty($vatValue)) {
            if (!$this->verifyNumberFormat($fee) || !$this->verifyVatFormat($vatValue)) {
                
                return [false, null];
            }
            return [true, hash('sha512', "$stamp&$site&$amount&$fee&$vatValue&$this->mac")];
        } elseif (!empty($fee)) {
            if (!$this->verifyNumberFormat($fee)) {
                return [false, null];
            }
            return [true, hash('sha512', "$stamp&$site&$amount&$fee&$this->mac")];
        } elseif (!empty($vatValue)) {
            if (!$this->verifyVatFormat($vatValue)) {
                return [false, null];
            }
            return [true, hash('sha512', "$stamp&$site&$amount&$vatValue&$this->mac")];
        } 
        return [true, hash('sha512', "$stamp&$site&$amount&$this->mac")];
    } 


    # function to generate the epassi HTML-form. Returns the array [bool, form]

    public function generateEpassiForm($stamp, $amount, $fee = "", $vatValue = "", $buttonText = PAY_BUTTON_TEXT) 
    {
        [$ok, $hash] = $this->generateSHA512($stamp, $this->site, $amount, $fee, $vatValue);

        if ($ok) {
            $form = "<form action='" . $this->epassiApiUrl . "' method='post'>";
            $form .= "<input type='hidden' name='STAMP' value='" . $stamp . "'>";
            $form .= "<input type='hidden' name='SITE' value='" . $this->site . "'>";
            $form .= "<input type='hidden' name='AMOUNT' value='" . $amount . "'>";
            $form .= "<input type='hidden' name='FEE' value='" . $fee . "'>";               # If fee is empty, should the field be included?
            $form .= "<input type='hidden' name='VAT_VALUE' value='" . $vatValue . "'>";    # If vatValue is empty, should the field be included?
            $form .= "<input type='hidden' name='REJECT' value='" . $this->rejectUrl . "'>";
            $form .= "<input type='hidden' name='CANCEL' value='" . $this->cancelUrl . "'>";
            $form .= "<input type='hidden' name='RETURN' value='" . $this->returnUrl . "'>";
            $form .= "<input type='hidden' name='MAC' value='" . $hash . "'>";
            $form .= "<input type='submit' value='" . $buttonText . "'>";
            $form .= "</form>";

            return [true, $form];
        }

        return [false, null];

        
    }
  }



class EpassiVerifier 
{
    private $mac;

    public function __construct(
        $epassiKey, 
        $testing = false
    ) 
    {
        $this->mac = $epassiKey;
        if ($testing) {
            $this->mac = "1TRQVUMAUBX4";
        }
    }


    # sha512 verifier, returns true or false, depending on if the hash is correct
    # Note that MAC is the epassi secret key. Functionally, sha512 should be what the API refers to as MAC. Not confusing at all...
    # SHA512 structure: stamp&paid&mac
    private function verifySHA512($sha512, $stamp, $paid) 
    {
        $hash = hash('sha512', $stamp . "&" . $paid . "&" . $this->mac);
        if ($hash == $sha512) {
            return true;
        }

        return false;
    }


    # function to check if the response is valid. Arguments: POST parameters as array. Returns array [bool, stamp, paid]
    public function verifyPaymentConfirmation($parameters)
    {
        if (isset($parameters['STAMP']) && isset($parameters['PAID']) && isset($parameters['MAC'])) {
            $stamp = $parameters['STAMP'];
            $paid = $parameters['PAID'];
            $mac = $parameters['MAC'];

            if ($this->verifySHA512($mac, $stamp, $paid)) {
                return [true, $stamp, $paid];
            }
        }

        return [false, null, null];
    }


    # function to check if the payment was rejected or cancelled. Returns array [bool, stamp, error], where bool indicates if the response was valid
    public function checkRejection($parameters) 
    {
        $error = "";
        $stamp = "";
        if (isset($parameters['stamp'])) {
            $stamp = $parameters['stamp'];
            if (isset($parameters['error'])) {
                $error = $parameters['error'];
            }
        } else {
            return [false, null, null];
        }

        return [true, $stamp, $error];
    }


    # function to check the error type. Returns [bool, error_description]
    public function checkErrorType($error) 
    {
        switch ($error) {
            case ERROR_INVALID_REQ:
                return [true, ERROR_INVALID_REQ_TEXT];
            case ERROR_INVALID_SIGNATURE:
                return [true, ERROR_INVALID_SIGNATURE_TEXT];
            case ERROR_SITE_ERR:
                return [true, ERROR_SITE_ERR_TEXT];
            default:
                return [false, null];
        }
    }


    # function to check if the error is valid. Returns true or false
    public function isErrorValid($error) 
    {
        if ($error == ERROR_INVALID_REQ || $error == ERROR_INVALID_SIGNATURE || $error == ERROR_SITE_ERR) {
            return true;
        }

        return false;
    }

}
?>