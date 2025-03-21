<?php
namespace PaymentProcessor;

require_once 'payment.php';
require_once 'epassi_generator.php';
require_once "epassi_verifier.php";
require_once 'payment_system_definitions.php';

require_once dirname(__FILE__, VISMA_PATH_LEVELS) . VISMA_LIBRARY_LOCATION; // Load VismaPay library
use VismaPay;
use Epassi;



class PaymentProcessor
{
    private $dbConnection = null;

    private $epassiGenerator = null;
    private $epassiVerifier = null;
    private $epassiButtonText = "";

    private $vismaPay = null;
    private $vismaReturnUrl = "";
    private $vismaNotifyUrl = "";
    
    private $paymentProviders = [];

    private $createPaymentSTMT = null;
    private $checkOrderPaymentSTMT = null;
    private $updateOrderPaymentSTMT = null;
    private $getOrderPriceSTMT = null;
    private $getOrderProductsSTMT = null;
    private $getOrderTicketsSTMT = null;
    private $closePaymentSTMT = null;
    private $getPaymentSTMT = null;
    private $deletePaymentSTMT = null;


    // TODO: !!TEST!! free (internal) payments
    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $paymentProviders[] = PAYMENT_PROVIDER_INTERNAL;

        // Initialize database statements
        try {
            // Payment statements
            $this->createPaymentSTMT = $this->dbConnection->prepare("INSERT INTO payments (payment_status, filing_identifier, payment_provider, created_at) VALUES (?, ?, ?, ?)");
            $this->getPaymentOrder = $this->dbConnection->prepare("SELECT * FROM orders WHERE payment_id = ?");
            $this->closePaymentSTMT = $this->dbConnection->prepare("UPDATE payments SET payment_status = ?, psp_transaction_id = ? WHERE filing_identifier = ?");
            $this->getPaymentSTMT = $this->dbConnection->prepare("SELECT * FROM payments WHERE filing_identifier = ?");
            $this->getPaymentWithPSPTokenSTMT = $this->dbConnection->prepare("SELECT * FROM payments WHERE psp_token = ?");
            $this->deletePaymentSTMT = $this->dbConnection->prepare("DELETE FROM payments WHERE payment_id = ?");
            $this->updatePaymentPSPTokenSTMT = $this->dbConnection->prepare("UPDATE payments SET psp_token = ? WHERE payment_id = ?");
            $this->updatePaymentPSPIdempotencyKeySTMT = $this->dbConnection->prepare("UPDATE payments SET psp_idempotency_key = ? WHERE payment_id = ?");

            // Order statements
            $this->getOrderSTMT = $this->dbConnection->prepare("SELECT * FROM orders WHERE order_uid = ?");
            $this->updateOrderPaymentSTMT = $this->dbConnection->prepare("UPDATE orders SET payment_id = ? WHERE order_uid = ?");
            $this->getOrderProductsSTMT = $this->dbConnection->prepare("SELECT * FROM order_products WHERE order_id = ?");
            $this->getOrderTicketsSTMT = $this->dbConnection->prepare("SELECT * FROM tickets WHERE order_id = ?");
            $this->deleteOrderPaymentSTMT = $this->dbConnection->prepare("DELETE FROM t1 USING payments AS t1 INNER JOIN orders AS t2 ON t1.payment_id = t2.payment_id WHERE t2.order_uid = ?"); 
            
            // Customer statements
            $this->getCustomerSTMT = $this->dbConnection->prepare("SELECT * FROM customers WHERE customer_id = ?");

            // Order product statements
            $this->getOrderProductsSTMT = $this->dbConnection->prepare("SELECT * FROM products INNER JOIN order_products ON products.product_id = order_products.product_id WHERE order_id = ?");
            $this->getOrderTicketsSTMT = $this->dbConnection->prepare("SELECT * FROM tickets WHERE order_id = ?");
        }
        catch (Exception $e) {
            throw new Exception(PAYMENT_SYSTEM_STATEMENT_ERROR . ": " . $e->getMessage());
        }
        
    }

    // returns bool
    
    public function initializeEpassi($epassiLogin, $epassiKey, $epassiReturnUrl, $epassiCancelUrl, $epassiRejectUrl, $epassiTesting, $epassiButtonText){
        if ($epassiLogin == null || $epassiKey == null) {
            throw new Exception("Epassi login and key are required");
        }
        if ($epassiReturnUrl == null || $epassiCancelUrl == null || $epassiRejectUrl == null) {
            throw new Exception("Epassi return, cancel and reject urls are required");
        }
        $this->epassiButtonText = $epassiButtonText;
        $this->epassiGenerator = new Epassi\EpassiGenerator($epassiLogin, $epassiKey, $epassiReturnUrl, $epassiCancelUrl, $epassiRejectUrl, EPASSI_API_URL, $epassiTesting);
        $this->epassiVerifier = new Epassi\EpassiVerifier($epassiKey, $epassiTesting);
        $this->paymentProviders[] = PAYMENT_PROVIDER_EPASSI;
        return true;
    }


    // returns bool
    
    public function initializeVismapay($vismaApiKey, $vismaPrivateKey, $vismaReturnUrl, $vismaNotifyUrl){
        if ($vismaApiKey == null || $vismaPrivateKey == null) {
            throw new Exception("VismaPay API key and private key are required");
        }
        if ($vismaReturnUrl == null || $vismaNotifyUrl == null) {
            throw new Exception("VismaPay return and notify urls are required");
        }
        $this->vismaReturnUrl = $vismaReturnUrl;
        $this->vismaNotifyUrl = $vismaNotifyUrl;
        $this->vismaPay = new VismaPay\VismaPay($vismaApiKey, $vismaPrivateKey);
        $this->paymentProviders[] = PAYMENT_PROVIDER_VISMA;
        return true;
    }


    private function generatePaymentIdentifier($orderUID, $provider, $time)
    {
        $yymmDate = date("ym", $time);
        return COMPANY_CODE . "-" . $yymmDate . "-" . hash('sha1', $orderUID . $provider . date("Y-m-d H:i:s", $time)); 
    }


    private function verifyPaymentIdentifier($identifier, $orderUID, $provider, $timestamp)
    {
        $yymmDate = substr($timestamp, 2, 2) . substr($timestamp, 5, 2);
        return $identifier == COMPANY_CODE . "-". $yymmDate . "-" . hash('sha1', $orderUID . $provider . $timestamp);
    }


    private function generateMobilePayToken($orderUID, $time, $amount)
    {
        return hash('sha512', $orderUID . $time . $amount . "WIP");
    }


    // Returns provider name or null
    
    private function getPaymentProvider($provider){
        if (in_array($provider, $this->paymentProviders)) {
            return $provider;
        } else {
            return null;
        }
    }


    // Returns [bool, orderData]
    // Where bool is true if the query was successful and orderData is the order data if found
    
    private function getOrder($orderUID)
    {
        $this->getOrderSTMT->bind_param("s", $orderUID);
        if ($this->getOrderSTMT->execute()) {
            $result = $this->getOrderSTMT->get_result();
            if ($result->num_rows == 1) {
                return [true, $result->fetch_assoc()];
            }
            return [true, null];
        }
        return [false, null];
    }

    
    // Returns [bool, orderData]
    // Where bool is true if the query was successful and orderData is the order data if found
    
    private function getOrderWithPayment($paymentID)
    {    
        $this->getPaymentOrder->bind_param("s", $paymentID);
     
        if ($this->getPaymentOrder->execute()) {
            $result = $this->getPaymentOrder->get_result();
            if ($result->num_rows == 1) {
                return [true, $result->fetch_assoc()];
            }
            return [true, null];
        }
        return [false, null];
    }


    // Returns true if the order payment reference was successfully updated
    
    private function setOrderPayment($paymentID, $orderUID)
    {
        $this->updateOrderPaymentSTMT->bind_param("is", $paymentID, $orderUID);
        if (!$this->updateOrderPaymentSTMT->execute()){
            // Order payment reference update failed
            return false;
        }
        return true;
    }


    // Returns true if the order payment reference was successfully cleared
    // Used to clear the payment reference if needed
    
    public function clearOrderPayment($orderUID)
    {
        try {
            $this->setOrderPayment(null, $orderUID);
            $this->deleteOrderPaymentSTMT->bind_param("s", $orderUID);
            if ($this->deleteOrderPaymentSTMT->execute()) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }



    // Returns [bool, paymentID]
    // Where bool is true if the query was successful and paymentID is the id of the created payment
    
    private function createPaymentEntry($status, $filingIdentifier, $provider, $createdTimestamp)
    {
        $this->createPaymentSTMT->bind_param("ssss", $status, $filingIdentifier, $provider, $createdTimestamp);
        if ($this->createPaymentSTMT->execute()) {
            return [true, $this->createPaymentSTMT->insert_id];
        }
        return [false, null];
    }


    // Returns [bool, paymentData]
    // Where bool is true if the query was successful and paymentData is the data if found
    
    private function getPayment($filingIdentifier)
    {
        $this->getPaymentSTMT->bind_param("s", $filingIdentifier);       

        if ($this->getPaymentSTMT->execute()) {
            $result = $this->getPaymentSTMT->get_result();
            if ($result->num_rows == 1) {
                return [true, $result->fetch_assoc()];
            } else {
                return [true, null];
            }
        }
        // Throw error, maybe?
        return [false, null];
    }


    // Returns [bool, paymentData]
    // Where bool is true if the query was successful and paymentData is the data if found

    private function getPaymentWithPSPToken($pspToken)
    {
        $this->getPaymentWithPSPTokenSTMT->bind_param("s", $pspToken);
        if ($this->getPaymentWithPSPTokenSTMT->execute()) {
            $result = $this->getPaymentWithPSPTokenSTMT->get_result();
            if ($result->num_rows == 1) {
                return [true, $result->fetch_assoc()];
            } else {
                return [true, null];
            }
        }
        return [false, null];
    }


    // Returns true if the payment data was successfully updated
    
    private function setPaymentData($status, $pspTransactionID, $filingIdentifier) 
    {
        $this->closePaymentSTMT->bind_param("sss", $status, $pspTransactionID, $filingIdentifier);
        if ($this->closePaymentSTMT->execute()) {
            return true;
        } else {
            return false;
        }
        return false;
    }


    // Returns true if the payment was successfully deleted
    
    private function deletePayment($paymentID)
    {
        $this->deletePaymentSTMT->bind_param("s", $paymentID);

        if ($this->deletePaymentSTMT->execute()) {
            if ($this->deletePaymentSTMT->affected_rows == 1) {
                return true;
            }
            return false;
        }
        return false;
    }


    // Returns true if the payment PSP token was successfully updated
    
    private function setPaymentPSPToken($paymentID, $pspTransactionID)
    {
        $this->updatePaymentPSPTokenSTMT->bind_param("si", $pspTransactionID, $paymentID);
        if ($this->updatePaymentPSPTokenSTMT->execute()) {
            return true;
        }
    }


    // Returns true if the payment Idempotency key was successfully updated

    private function setPaymentIdempotencyKey($paymentID, $idempotencyKey)
    {
        $this->updatePaymentPSPIdempotencyKeySTMT->bind_param("si", $idempotencyKey, $paymentID);
        if ($this->updatePaymentPSPIdempotencyKeySTMT->execute()) {
            return true;
        }
    }


    // Collect customer data from customers table
    // Returns [bool, customerData]
    // Where bool is true if the query was successful and customerData is the data if found
    
    private function getCustomer($customerID)
    {
        $this->getCustomerSTMT->bind_param("i", $customerID);
        if ($this->getCustomerSTMT->execute()) {
            $result = $this->getCustomerSTMT->get_result();
            if ($result->num_rows == 1) {
                return [true, $result->fetch_assoc()];
            }
            return [true, null];
        }
        return [false, null];
    }


    // Collect product ids from order_products table and collect matching products from products table
    // Returns [bool, products + order_products]
    
    private function getOrderProducts($orderID)
    {
        $this->getOrderProductsSTMT->bind_param("i", $orderID);
        if ($this->getOrderProductsSTMT->execute()) {
            $result = $this->getOrderProductsSTMT->get_result();
            if ($result->num_rows > 0) {
                return [true, $result->fetch_all(MYSQLI_ASSOC)];
            }
            return [true, null];
        }
    }


    // Collect ticket ids from tickets table
    // Returns [bool, tickets]
    // Where bool is true if the query was successful and tickets is the data if found
    
    private function getOrderTickets($orderID)
    {
        $this->getOrderTicketsSTMT->bind_param("i", $orderID);
        if ($this->getOrderTicketsSTMT->execute()) {
            $result = $this->getOrderTicketsSTMT->get_result();
            if ($result->num_rows > 0) {
                return [true, $result->fetch_all(MYSQLI_ASSOC)];
            }
            return [true, null];
        }    
    }


    // Get data from payment form, which includes the selected payment provider.
    // Collect necessary data for payment provider
    // Initialize the payment through selected payment provider's library.
    // Returns a data that contains link to payment provider that can be used in the payment process.
    // Returns payment object
    
    public function initializePayment($orderUID, $provider)
    {
        $payment = new Payment();
        if ($this->getPaymentProvider($provider) != null) {
            // create payment to database, get the paymentID
            $time = time();
            $dateTime = date("Y-m-d H:i:s", $time);
            $paymentIdentifier = $this->generatePaymentIdentifier($orderUID, $provider, $time);
            
            // Get the order data
            [$orderFound, $orderData] = $this->getOrder($orderUID);
            if (!$orderFound || $orderData == null) {
                // Order not found
                $payment->setError(ERROR_INVALID_TRANSACTION);
                return $payment;
            }

            // Ensure that the order is not already refrenced to a payment
            if ($orderData['payment_id'] != null) {
                $payment->setError(ERROR_DUPLICATE_PAYMENT);
                return $payment;
            }

            // Get the order price
            $orderPrice = $orderData['order_price_incl_vat'];
            if($orderPrice == null) {
                $payment->setError(ERROR_INVALID_TRANSACTION);
                return $payment;
            }

            // Create new payment entry
            [$inserted, $insertId] = $this->createPaymentEntry(PAYMENT_STATUS_PROCESSING, $paymentIdentifier, $provider, $dateTime);
            if (!$inserted) {
                $payment->setError(ERROR_DATABASE_INSERT);
                return $payment;
            }

            // Bind the payment to the order
            if (!$this->setOrderPayment($insertId, $orderUID)) {
                $payment->setError(ERROR_DATABASE_REFERENCE);
                $this->deletePayment($insertId);
                return $payment;
            }

            // Actual payment creation to the selected payment provider
            if ($orderPrice > 0) {
                if ($provider == PAYMENT_PROVIDER_EPASSI) {
                    [$created, $paymentData] = $this->epassiGenerator->generateEpassiForm(stamp: $paymentIdentifier, amount: $orderPrice, buttonText: $this->epassiButtonText);
                    if ($created) {
                        $payment->setState(true);
                        $payment->setPaymentLink($paymentData);
                        $payment->setPaymentIdentifier($paymentIdentifier);
                        return $payment;
                    }else{
                        // Epassi form generation failed
                        $payment->setError(ERROR_PROVIDER_ERROR);
                        $this->setOrderPayment(null, $orderUID);
                        $this->deletePayment($insertId);
                        return $payment;

                    }
                
                } elseif ($provider == PAYMENT_PROVIDER_VISMA) {
            
                    // Get customer data
                    [$customerFound, $customerData] = $this->getCustomer($orderData['customer_id']);
                    if ($customerFound && $customerData != null) {
                        // Customer data for Visma should contain at least: firstname, lastname, email
                        // Rest are HIGHLY recommended
                        $vismaCustomer = [
                            "firstname" => $customerData['first_name'],
                            "lastname" => $customerData['last_name'],
                            "email" => $customerData['email']
                        ];
                        if ($customerData['address'] != null) {
                            $vismaCustomer["address_street"] = $customerData['address'];
                        }
                        if ($customerData['city'] != null) {
                            $vismaCustomer["address_city"] = $customerData['city'];
                        }
                        if ($customerData['postal_code'] != null) {
                            $vismaCustomer["address_zip"] = $customerData['postal_code'];
                        }
                    }

                    [$productsFound, $products] = $this->getOrderProducts($orderData['order_id']);
                    [$ticketsFound, $tickets] = $this->getOrderTickets($orderData['order_id']);

                    $vismaProducts = [];

                    if ($ticketsFound && $tickets != null) {
                        foreach ($tickets as $ticket){
                            $vismaProducts[] = [
                                "id" => $ticket['ticket_id'],
                                "title" => $ticket['name'],
                                "count" => 1,
                                "pretax_price" => $ticket['price_excl_vat'] * 100,
                                "tax" => $ticket['vat_percentage'],
                                "price" => $ticket['price_incl_vat'] * 100,
                                "type" => VISMA_PRODUCT_TYPE_NORMAL
                            ];
                        }
                    }

                    if ($productsFound && $products != null) {
                        foreach ($products as $product){
                            $vismaProducts[] = [
                                "id" => $product['product_id'],
                                "title" => $product['name'],
                                "count" => $product['amount'],
                                "pretax_price" => $product['unit_price_excl_vat'] * 100,
                                "tax" => $product['vat_percentage'],
                                "price" => $product['unit_price_incl_vat'] * 100,
                                "type" => VISMA_PRODUCT_TYPE_NORMAL
                            ];
                        }
                                             
                    }

                    $totalPrice = 0;
                    foreach ($vismaProducts as $product) {
                        $totalPrice += $product['price'] * $product['count']; 
                    }
                    if ($totalPrice != $orderPrice * 100) {
                        $payment->setError(ERROR_MISMATCHED_TOTALPRICE);
                        $this->setOrderPayment(null, $orderUID);
                        $this->deletePayment($insertId);
                        return $payment;
                    }

                    // Add Charge
                    $this->vismaPay->addCharge(
                        array(
                            'order_number' => $paymentIdentifier,
                            'amount' => $orderPrice * 100,
                            'currency' => VISMA_DEFAULT_CURRENCY
                        )
                    );

                    // Add Customer
                    $this->vismaPay->addCustomer($vismaCustomer);

                    // Add Products
                    foreach ($vismaProducts as $product) {
                        $this->vismaPay->addProduct($product);
                    }

                    // Add Payment
                    $this->vismaPay->addPaymentMethod(
                        array(
                            'type' => VISMA_PAYMENT_TYPE,
                            'return_url' => $this->vismaReturnUrl,
                            'notify_url' => $this->vismaNotifyUrl,
                            'lang' => VISMA_DEFAULT_LANGUAGE,
                            'token_valid_until' => VISMA_DEFAULT_TOKEN_VALIDITY
                        )
                    );

                    try {
                        $result = $this->vismaPay->createCharge();
                        if ($result->result == VISMA_RESULT_SUCCESS) {
                            if ($this->setPaymentPSPToken($insertId, $result->token)) {
                                $payment->setPaymentIdentifier($paymentIdentifier);
                                $payment->setState(true);
                                $payment->setPaymentLink($this->vismaPay::API_URL . '/token/' . $result->token);
                                return $payment;
                            } 
                        } 
                          
                        // Payment creation failed, remove order payment refrence
                        $this->setOrderPayment(null, $orderUID);
                        $this->deletePayment($insertId);
                        $payment->setError(ERROR_PROVIDER_ERROR . ": " . $result->errors);
                        return $payment;

                    } catch (Exception $e) {
                        $payment->setError(ERROR_PROVIDER_ERROR . ": " . $e->getMessage());
                        return $payment;
                    }
                }
                $payment->setError(ERROR_INVALID_PROVIDER_TYPE);
            }else{
                // TODO: How are free (0 cost) orders handled?
                if ($provider == PAYMENT_PROVIDER_INTERNAL) {
                    // Internal 0 cost payment
                }
                $payment->setError(ERROR_INVALID_PROVIDER_TYPE);
            }  
        } else {
            $payment->setError(ERROR_INVALID_PROVIDER);
        }
        return $payment;
    }
        

    public function getPaymentwithToken($token) {
        [$paymentFound, $paymentData] = $this->getPaymentWithPSPToken($token);
        if ($paymentFound) {
            if ($paymentData == null) {
                return null;
            }
            return $paymentData;
        }
        return null;
    }




    // Process the payment response from the payment provider.
    // This will verify the data and finalize the transaction if the payment was successful.
    // Returns: payment object

    public function processPayment($provider, $response, $method)
    {
        $payment = new Payment();
        $orderUID = null;
        $result = false;
        if ($this->getPaymentProvider($provider) != null) {
            
            if ($provider == PAYMENT_PROVIDER_EPASSI) {
                // Verify Epassi payment response: POST is for successful actions, GET is for rejections/cancellations
                if ($method == "POST") {
                    [$ok, $identifier, $pspTransactionID] = $this->epassiVerifier->verifyPaymentConfirmation($response);
                    [$result, $orderUID, $error] = $this->finishTransaction($ok, $identifier, $pspTransactionID);
                    
                } elseif ($method == "GET"){
                    [$verified, $identifier, $error] = $this->epassiVerifier->checkRejection($response);
                    if ($verified) {
                        [$result, $orderUID, $error] = $this->finishTransaction(false, $identifier, $pspTransactionID);
                    }else{
                        // Not legit response 
                        $error = ERROR_INVALID_RESPONSE;
                    }
                }  
            } elseif ($provider == PAYMENT_PROVIDER_VISMA) {
                // Visma responds with GET, so both successful and failed transactions are handled here
                if ($method == "GET"){
                    $return = $this->vismaPay->checkReturn($response);
                    if ($return->RETURN_CODE == VISMA_RESULT_SUCCESS) {
                        [$result, $orderUID, $error] =  $this->finishTransaction(true, $return->ORDER_NUMBER);
                    } else {
                        switch ($return->RETURN_CODE) {
                            case VISMA_RETURN_FAILED:
                                $error = ERROR_PAYMENT_CANCELLED;
                                break;
                            case VISMA_RETURN_ADDITIONAL_ACTION:
                                $error = ERROR_VISMA_ADDITIONAL_ACTION;
                                break;
                            case VISMA_RETURN_MAINTANANCE_BREAK:
                                $error = ERROR_MAINTANANCE_BREAK;
                                break;
                            default:
                                $error = ERROR_INVALID_RESPONSE;
                        }
                    }      
                }else{
                    // Not legit response
                    $error = ERROR_INVALID_RESPONSE;
                }

            }
            $payment->setError($error);
            $payment->setOrderUID($orderUID);
            $payment->setState($result);
        } else {
            $payment->setError(ERROR_INVALID_PROVIDER);
        }
        return $payment;
    }

    
    // Returns [bool, order_uid, error]: 
    // bool: True if the transaction was successfully completed, False otherwise.
    // order_uid: The order UID of the transaction.
    // error: An error message if the transaction was not successfully completed.
    // Find ongoing payment data from database using identifier
    // Finalize the transaction. This means that payment is marked as paid and completed & the related
    // order is marked completed in the database.  Alternatively, the transaction was cancelled and
    // the payment is removed. Cancelled payments should be logged correctly.

    private function finishTransaction($completed, $filingID, $pspTransactionID = "")  
    {
        // Complete the transaction in the database
        // Update the transaction status to "paid"
        // Update the transaction payment stamp

        [$paymentFound, $paymentData] = $this->getPayment($filingID);       

        if ($paymentFound) {
            if ($paymentData == null) {
                return [false, null, ERROR_TRANSACTION_NOT_FOUND];
            }

            [$orderFound, $orderData] = $this->getOrderWithPayment($paymentData['payment_id']);
            if (!$orderFound || $orderData == null) {
                return [false, null, ERROR_TRANSACTION_NOT_FOUND];
            }
            

            $valid = $this->verifyPaymentIdentifier(
                $filingID, 
                $orderData['order_uid'],
                $paymentData['payment_provider'], 
                $paymentData['created_at']
            );

            if ($valid) {
                if ($completed) {
                    // Update the payment status to "success"
                    if ($this->setPaymentData(PAYMENT_STATUS_COMPLETED, $pspTransactionID, $filingID)) {
                        return [true, $orderData['order_uid'], ""];
                    } else {
                        // Payment status update failed
                        return [false, null, ERROR_DATABASE_ERROR];
                    }
                } else {
                    // Payment has been rejected/cancelled
                    // Remove the payment refrence from the order and then remove the payment.
                    $this->setOrderPayment(null, $orderData['order_uid']);
                    
                    if ($this->deletePayment($paymentData['payment_id'])) {
                        return [true, $orderData['order_uid'], ""];
                    } else {
                        return [false, null, ERROR_DATABASE_ERROR];
                    }
                }
            } else {
                return [false , null, ERROR_INVALID_TRANSACTION];
            }
        } else {
            return [false , null, ERROR_TRANSACTION_NOT_FOUND];
        }
    }    
}


?>