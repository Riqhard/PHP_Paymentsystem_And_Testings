<?php
require_once "payment_processor.php";

$db_server = "localhost";
$db_username = "root";
$db_password = "";
$DB = "store_testing_database";
$port = 3306;



$epassiReturn = "https://kilpimaari-htc3def2dpckc4ht.westeurope-01.azurewebsites.net/payment_confirm.php";


try {
    $mysqli = new mysqli($db_server, $db_username, $db_password, $DB, $port);
    if ($mysqli->connect_error) {
        die("Yhteyden muodostaminen epäonnistui: " . $mysqli->connect_error);
        }
    $mysqli->set_charset("utf8");
}
catch (Throwable $e) {
    die("Virhe yhteyden muodostamisessa: " . $e->getMessage());
}


$paymentProcessor = new PaymentProcessor\PaymentProcessor($mysqli);


try {
    $paymentProcessor->initializeEpassi(epassiLogin: " ", epassiKey: " ", epassiReturnUrl:  $epassiReturn, epassiCancelUrl: $epassiReturn, epassiRejectUrl: $epassiReturn, epassiTesting: true, epassiButtonText: "Epassi Payment Button");
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}


function parsia($payment){
    if (isset($_POST["Delete"])){
        echo "<hr> Payment Deleted <br>";
    }else{
        if ($payment->getPaymentLink() != null){
            echo $payment->getPaymentLink() . "<br>";
        }
        echo "<hr> State: " . (int) $payment->getState() . "<br>";
        echo "OrderUID: " . (string) $payment->getOrderUID() . "<br>";
        echo "Error: " . $payment->getError() . "<br>";
        echo "PIndentifier: " . $payment->getPaymentIdentifier() . "<br>";
        // Makes a button that redirects to the payment link
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epassi Payment Tester</title><br>
    <link rel="stylesheet" href="../style.css">

</head>
<body>
    
<br>
<div class="fullContainer1">
<h1>Epassi Payment Tester</h1>

    <div class="payment-container">
    <form action="" method="post">
        <input type="text" name="order_uid" value="O12345">
        <br>
        <input type="submit" value="Submit">
    </form>
    </div>
    <div class="payment-container">
        Deleting Payment to Order
     <form action='' method='POST'>
        OrderUID: <input type='text' name='ORDERUID' value=''><br>
        <input type='text' name='Delete' value='' hidden><br>
        <input type='submit' value='Submit'>
     </form>
     </div>
    <?php
    echo "<br><div class='payment-container'>";
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST["order_uid"])){
                $order_uid = $_POST["order_uid"];
                try{
                    $payment = $paymentProcessor->initializePayment($order_uid, "epassi");
                    parsia($payment);
    
                }catch (Throwable $e){
                    echo "Error: " . $e->getMessage();
                }catch (Exception $e){
                    echo "Error E: " . $e->getMessage();
                }
                
            }else{
                $order_uid = "";
            }
            if (isset($_POST["Delete"])){
                $payStatus = $paymentProcessor->clearOrderPayment($_POST["ORDERUID"]); 
                if ($payStatus){
                    echo "<br> We Deleted Payment <br>
                    Payment OrderUID: " . $_POST["ORDERUID"];
                }else{
                    echo "<br> The Payment you wanted to Deleted cannot be deleted. It does not exist or the payment is already Completed<br>
                    Payment OrderUID: " . $_POST["ORDERUID"];
                }
            } 
        }
    echo "</div>";
    ?>

</div>


</body>
</html>

