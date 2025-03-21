<?php
namespace Epassi;
require_once 'epassi_definitions.php';


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