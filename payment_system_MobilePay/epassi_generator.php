<?php
namespace Epassi;
require_once 'epassi_definitions.php';
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
            $this->mac = "1TRQVUMAUBX4";
            $this->site = "77190";
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

?>