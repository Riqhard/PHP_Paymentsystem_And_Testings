<?php 
require_once "db.php";
// Pakollista tietoa
require_once "payment_processor.php";
// **************

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


// Pakollista tietoa
$vismaReturn = "http://localhost/PHP_API_Testing/payment_system/vismapay_testing.php";
$vismaNotify = "http://localhost/PHP_API_Testing/payment_system/vismapay_testing_notify.php";
    $vismaKey = "VismaKey";
    $vismaApiKey = "VismaApiKey";
// **************


try{
    // Pakollista tietoa
    $paymentProcessor = new PaymentProcessor\PaymentProcessor($mysqli);
    $paymentProcessor->initializeVismapay($vismaApiKey, $vismaKey, $vismaReturn, $vismaNotify);
    // **************
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}

function parsia($payment){
    if (isset($_POST["Delete"])){
        echo "<hr> Payment Deleted <br>";
    }else{
        if ($payment->getPaymentLink() != null){
            echo "<div class='payment-container'><a href='" . $payment->getPaymentLink() . "' class='button'>Go To Vismapay</a></div><hr>";
        }
        echo "<hr> State: " . (int) $payment->getState() . "<br>";
        echo "OrderUID: " . (string) $payment->getOrderUID() . "<br>";
        echo "Error: " . $payment->getError() . "<br>";
        echo "PLink: " . $payment->getPaymentLink() . "<br>";
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
    <title>VismaPay Tester</title><br>
    <link rel="stylesheet" href="../style.css">

</head>
<body>
<div class="fullContainer1">
<h1>Vismapay Tester Notify</h1>

<?php 
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ORDERUID"])) {
            $payStatus = false;
            try{
                    // Pakollista tietoa
                $payment = $paymentProcessor->initializePayment($_POST["ORDERUID"], PAYMENT_PROVIDER_VISMA);
                    // **************
                parsia($payment);
            }catch (Throwable $e){
                echo "Error: " . $e->getMessage();
            }catch (Exception $e){
                echo "Error E: " . $e->getMessage();
            }

            if (isset($_POST["Delete"])){
                $payStatus = $paymentProcessor->clearOrderPayment($_POST["ORDERUID"]); 
                if ($payStatus){
                    echo "<br> We Deleted Payment with OrderUID: " . $_POST["ORDERUID"];
                }else{
                    echo "<br> We DIDN'T Deleted Payment with OrderUID: " . $_POST["ORDERUID"];
                }
            } 

        }elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["AUTHCODE"])){      
            $get = $_GET;
            if ($get["SETTLED"] == ""){
                unset($get["SETTLED"]);
            }
            try{
                // Pakollista tietoa
                $payment2 = $paymentProcessor->processPayment(PAYMENT_PROVIDER_VISMA, $get, "GET");  
                // ************** 
                parsia($payment2);  
            }catch (Throwable $e){
                echo "Error: " . $e->getMessage();
            }    
        }

        $authCode = "";
        $returnCode = "";
        $orderNumber = "";
        $settled = "";

        if ($_SERVER["REQUEST_METHOD"] == "GET") {
            if (isset($_GET["SETTLED"])){
                echo "<br>";
                $authCode = $_GET["AUTHCODE"];
                $returnCode = $_GET["RETURN_CODE"];
                $orderNumber = $_GET["ORDER_NUMBER"];
                $settled = $_GET["SETTLED"];
                echo "AUTHCODE: " . $authCode . "<br>";
                echo "RETURN_CODE: " . $returnCode . "<br>";
                echo "ORDER_NUMBER: " . $orderNumber . "<br>";
                echo "SETTLED: " . $settled . "<br>";
                echo "<hr>";
            }
        }
        ?>
</div>
    

</div>

</body>
</html>
