<?php
include_once "payment_processor.php";



$secretKey = "1TRQVUMAUBX4";
$returnUrl = "https://kilpimaari-htc3def2dpckc4ht.westeurope-01.azurewebsites.net/payment_confirm.php";
$rejectUrl = "https://kilpimaari-htc3def2dpckc4ht.westeurope-01.azurewebsites.net/payment_confirm.php";
$cancelUrl = "https://kilpimaari-htc3def2dpckc4ht.westeurope-01.azurewebsites.net/payment_confirm.php";


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Response Tester</title><br>
    <link rel="stylesheet" href="../style.css">

</head>
<body>
<div class="fullContainer1">
<h1>Response Tester</h1>
Form for testing the response handling:
<div class="payment-container">
<form action='' method='GET'>
    AUTHCODE: <input type='text' name='AUTHCODE' value=''><br>
    RETURN_CODE: <input type='text' name='RETURN_CODE' value=''><br>
    ORDER_NUMBER: <input type='text' name='ORDER_NUMBER' value=''><br>
    SETTLED: <input type='text' name='SETTLED' value=''><br>
    <input type='submit' value='Submit'>
 </form>
</div>

<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {  
  echo var_dump($_POST);
}elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
  echo var_dump($_GET);
}
?>
</div>
 
 
</body>
</html>