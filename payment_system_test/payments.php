<?php
require_once 'epassi.php';
require_once 'payment_system_definitions.php';



class PaymentProcessor
{
    private $dbConnection = null;
    private $epassiGenerator = null;
    private $epassiVerifier = null;
    private $buttonText = "";

    private $createPaymentSTMT = null;
    private $checkOrderPaymentSTMT = null;
    private $updateOrderPaymentSTMT = null;
    private $getOrderPriceSTMT = null;
    private $getOrderProductsSTMT = null;
    private $getOrderTicketsSTMT = null;
    private $closePaymentSTMT = null;
    private $getPaymentSTMT = null;
    private $deletePaymentSTMT = null;

    // STATEMENTS:
    // Statement $createPaymentSTMT = $mysqli->prepare("INSERT INTO payments (status, filing_identifier, created_timestamp) VALUES (?, ?, ?)");
    // Statement $checkOrderPaymentSTMT = $mysqli->prepare("SELECT order_uid FROM orders WHERE order_uid = ? AND payment_id IS NULL");
    // Statement $updateOrderPaymentSTMT = $mysqli->prepare("UPDATE orders SET payment_id = ? WHERE order_uid = ?");
    // Statement $getOrderPriceSTMT = $mysqli->prepare("SELECT order_price_incl_vat FROM orders WHERE order_uid = ?");



    public function __construct($dbConnection, $epassiLogin, $epassiKey, $buttonText, $returnUrl, $cancelUrl, $rejectUrl)
    {
        $this->dbConnection = $dbConnection;
        $this->buttonText = $buttonText;
        $this->epassiGenerator = new EpassiGenerator($epassiLogin, $epassiKey, $returnUrl, $cancelUrl, $rejectUrl, EPASSI_API_URL, EPASSI_TESTING);
        $this->epassiVerifier = new EpassiVerifier($epassiKey, EPASSI_TESTING);

        // Initialize database statements
        try {
            $this->createPaymentSTMT = $this->dbConnection->prepare("INSERT INTO payments (payment_status, filing_identifier, payment_provider, created_at) VALUES (?, ?, ?, ?)");
            $this->checkOrderPaymentSTMT = $this->dbConnection->prepare("SELECT order_uid FROM orders WHERE order_uid = ? AND payment_id IS NULL");
            $this->updateOrderPaymentSTMT = $this->dbConnection->prepare("UPDATE orders SET payment_id = ? WHERE order_uid = ?");
            $this->getOrderPriceSTMT = $this->dbConnection->prepare("SELECT order_price_incl_vat FROM orders WHERE order_uid = ?");
            $this->getOrderProductsSTMT = $this->dbConnection->prepare("SELECT * FROM order_products WHERE order_id = ?");
            $this->getOrderTicketsSTMT = $this->dbConnection->prepare("SELECT * FROM tickets WHERE order_id = ?");
            $this->closePaymentSTMT = $this->dbConnection->prepare("UPDATE payments SET payment_status = ?, psp_transaction_id = ? WHERE payment_id = ?");
            $this->getPaymentSTMT = $this->dbConnection->prepare("SELECT * FROM payments WHERE payment_id = ?");
            $this->deletePaymentSTMT = $this->dbConnection->prepare("DELETE FROM payments WHERE payment_id = ?");
        }
        catch (Exception $e) {
            die("Error in payment processor initialization: " . $e->getMessage());
        }
        // Initialize payment processor
        // Initialize the payment processors with the necessary data
        
    }


    private function generatePaymentIdentifier($orderID, $provider, $createdTimestamp)
    {
        return hash('sha512', $orderID . $provider . $createdTimestamp);
    }


    private function verifyPaymentIdentifier($hash, $orderID, $provider, $createdTimestamp)
    {
        return hash_equals($hash, hash('sha512', $orderID . $provider . $createdTimestamp));
    }

    // Returns provider name or null
    private function getPaymentProvider($provider){
        if (in_array($provider, PAYMENT_PROVIDERS)) {
            return $provider;
        } else {
            return null;
        }
    }


    public function createPaymentEntry($status, $filingIdentifier, $provider, $createdTimestamp)
    {
        $this->createPaymentSTMT->bind_param("isss", $status, $filingIdentifier, $provider, $createdTimestamp);
        return [$this->createPaymentSTMT->execute(), $this->createPaymentSTMT->insert_id];
    }



    // Get data from payment form, which includes the selected payment provider.
    // Collect necessary data for payment provider
    // Initialize the payment through selected payment provider's library.
    // Returns a data that contains link to payment provider that can be used in the payment process.
    public function initializeTransaction($orderUID, $provider)
    {
        // TODO: 
        //   -- Do we need to rollback changes if something fails? Probably.
        

        if ($this->getPaymentProvider($provider) != null) {
            // create payment to database, get the paymentID
            $timeStamp = date("Y-m-d H:i:s");
            $paymentIdentifier = $this->generatePaymentIdentifier($orderUID, $provider, $timeStamp);
     
            // Ensure that the order is not already refrenced to a payment
            $this->checkOrderPaymentSTMT->bind_param("s", $orderUID);
            $this->checkOrderPaymentSTMT->execute();
            if ($this->checkOrderPaymentSTMT->get_result()->num_rows == 1) {
                // Create new payment entry
                [$inserted, $insert_id] = $this->createPaymentEntry(PAYMENT_STATUS_ONGOING, $paymentIdentifier, $provider, $timeStamp);
                if ($inserted) {
                    $this->updateOrderPaymentSTMT->bind_param("is", $insert_id, $orderUID);
                    if ($this->updateOrderPaymentSTMT->execute()){
                        // Get the order price
                        $this->getOrderPriceSTMT->bind_param("i", $orderUID);
                        $this->getOrderPriceSTMT->execute();
                        $orderPrice = $this->getOrderPriceSTMT->get_result()->fetch_assoc()['order_price_incl_vat'];
                        if ($orderPrice > 0) {
                            if ($provider == PAYMENT_PROVIDER_EPASSI) {
                                [$ok, $paymentData] = $this->epassiGenerator->generateEpassiForm(stamp: $paymentIdentifier, amount: $orderPrice, buttonText: $this->buttonText);
                                if ($ok) {
                                    return [true, $paymentData, null];
                                }else{
                                    // Epassi form generation failed
                                    return [false, null, ERROR_PROVIDER_ERROR];
                                }
                            }elseif ($provider == PAYMENT_PROVIDER_VISMA) {
                                // Initialize Visma payment
                            }
                            return [false, null, ERROR_INVALID_PROVIDER_TYPE];
                        }else{
                            // TODO: How are free (0 cost) orders handled?
                            if ($provider == PAYMENT_PROVIDER_INTERNAL) {
                                // Internal 0 cost payment
                            }
                            return [false, null, ERROR_INVALID_PROVIDER_TYPE];
                        }  
                    }else{
                        // Order payment refrence update failed
                        return [false, null, ERROR_DATABASE_REFERENCE];
                    }
                }else{
                    // Payment creation failed
                    return [false, null, ERROR_DATABASE_INSERT];
                }
            }else{
                // Order already has a payment
                return [false, null, ERROR_DUPLICATE_PAYMENT];
            }
        }
        // Invalid payment provider
        return [false, null, ERROR_INVALID_PROVIDER];
    }
        

    private function getOrderItems($orderID)
    {
        // TODO: Implement this function (Later if needed)

        // Get order items that are being paid for
        // Fetch the order products using the orderID from "order_products" table
        // Fetch the order tickets using the orderID from "tickets" table
        // Calculate the total amount to be paid for the order

        // Statement $getOrderProductsSTMT = $mysqli->prepare("SELECT * FROM order_products WHERE order_id = ?");
        // Statement $getOrderTicketsSTMT = $mysqli->prepare("SELECT * FROM tickets WHERE order_id = ?");
        return true;
    }


    public function processPayment($provider, $response, $method)
    {
        // Check payment provider is in the list PAYMENT_PROVIDERS
        if (in_array($provider, PAYMENT_PROVIDERS)) {
            // Verify payment response through selected payment provider's library.
            if ($provider == PAYMENT_PROVIDER_EPASSI) {
                if ($method == "POST"){
                    // Verify ePassi payment response
                    [$ok, $identifier, $pspTransactionID] = $this->epassiVerifier->verifyPaymentResponse($response);
                    return finishTransaction($ok, $paymentID, $pspTransactionID);
                }elseif ($method == "GET"){
                    // Verify ePassi payment rejection
                    [$verified, $identifier, $error] = $this->epassiVerifier->verifyPaymentRejection($response);
                    if ($verified) {
                        [$ok, $paymentOrderID, $error] = finishTransaction(false, $paymentID, $pspTransactionID);
                        // Cancel the transaction in the database
                    }else{
                        // Not legit response
                    }
                }
            } elseif ($provider == PAYMENT_PROVIDER_VISMA) {
                // Verify Visma payment response
                // If valid, find transaction data from database using identifier
            }
        } else {
            return [false , null,  ERROR_INVALID_PROVIDER];
        }
    }


    
    // Returns [bool, order_uid, error]: 
    // bool: True if the transaction was successfully completed, False otherwise.
    // order_uid: The order UID of the transaction.
    // error: An error message if the transaction was not successfully completed.
    // Find ongoing payment data from database using identifier
    // Finalize the transaction. This means that payment is marked as paid and completed & the related
    // order is marked completed in the database.  Alternatively, the transaction was cancelled and
    // the payment is removed. Cancelled payments should be logged correctly.
    private function finishTransaction($completed, $paymentID, $pspTransactionID)  
    {
        // Complete the transaction in the database
        // Update the transaction status to "paid"
        // Update the transaction payment stamp



        // Statement $closePaymentSTMT = $mysqli->prepare("UPDATE payments SET payment_status = ?, psp_transaction_id = ? WHERE payment_id = ?");
        // Statement $getPaymentSTMT = $mysqli->prepare("SELECT * FROM payments WHERE payment_id = ?");
        // Statement $deletePaymentSTMT = $mysqli->prepare("DELETE FROM payments WHERE payment_id = ?");
        $this->getPaymentSTMT->bind_param("i", $paymentID);       

        if ($this->getPaymentSTMT->execute()) {
            $payment = $this->getPaymentSTMT->result()->fetch_assoc();

            $valid = $this->verifyPaymentIdentifier(
                $identifier, 
                $payment['orderID'], 
                $payment['provider'], 
                $payment['createdTimestamp']
            );

            if ($valid) {
                if ($completed) {
                    // TODO: Should Returns Order UID !NOT order id!
                    // Update the payment status to "success"
                    $this->closePaymentSTMT->bind_param("isi", PAYMENT_STATUS_SUCCESS, $pspTransactionID, $paymentID);
                    $this->closePaymentSTMT->execute();
                    return [true, $payment['orderID'], ""];

                }else{
                    // Remove reference from order
                    $this->updateOrderPaymentSTMT->bind_param("is", null, $transaction['orderID']);
                    $this->updateOrderPaymentSTMT->execute();
                    // Delete the payment
                    $this->deletePaymentSTMT->bind_param("i", $paymentID);
                    $this->deletePaymentSTMT->execute();
                    if ($this->deletePaymentSTMT->affected_rows == 1){
                        return [true, $transaction['orderID'], ""];
                    }else{        
                        // Something went horribly wrong  
                        // TODO: Figure out which error to return            
                        return [false, null, ERROR_INVALID_TRANSACTION];
                    }

                }

                
            } else {
                return [false , null, ERROR_INVALID_TRANSACTION];
            }
            
            // TODO: Implement the payment processing logic
        }else{
            return [false , null, ERROR_TRANSACTION_NOT_FOUND];
        }

    }    
}


?>