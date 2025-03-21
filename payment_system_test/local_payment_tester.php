<?php
require_once "db.php";
require_once "payments.php";

$returnUrl = "";
$rejectUrl = "";
$cancelUrl = "";


$paymentProcessor = new PaymentProcessor($mysqli, "", "", "Button Text", $returnUrl, $rejectUrl, $cancelUrl);


echo "paymentProcessor loaded<br><hr>";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "got post";
    [$ok, $paymentID, $error] = $paymentProcessor->processPayment("epassi", $_POST, "POST");
    if ($error){
        echo "<br>error: ";
        echo $error;
    }else{
        echo "<br>paymentID: ";
        echo $paymentID;
    }

}elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    [$ok, $paymentID, $error] = $paymentProcessor->processPayment;
}

?>

<html>
<body>

    <form method="post" action="">
        <label for="amount">STAMP:</label><br>
        <input type="text" id="STAMP" name="STAMP"><br><br>

        <label for="vat_value">MAC:</label><br>
        <input type="text" id="MAC" name="MAC"><br><br>
        
        <label for="fee">PAID:</label><br>
        <input type="text" id="PAID" name="PAID"><br><br>

        <input type="submit" value="Submit">
    </form>

</body>
</html>