<?php
require_once "payments.php";

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

$paymentProcessor = new PaymentProcessor($mysqli, "", "", "Button Text", $epassiReturn, $epassiReturn, $epassiReturn);

$element = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_uid = $_POST["order_uid"];
    echo "got post";
    [$ok, $element, $error] = $paymentProcessor->initializeTransaction($order_uid, "epassi");
    if ($error){
        echo "<br>error: ";
        echo $error;
    }
    echo "<br>got element";
    echo $element;

}

?>
<html>
<body>
<p> Process test payment</p>
<form action="" method="post">
    <input type="text" name="order_uid" value="O12345">
    <input type="submit" value="Submit">
</form>

<?php 
echo "<br><hr>";
if (!empty($element)) {
    echo $element;
}
?>

</body>
</html>
