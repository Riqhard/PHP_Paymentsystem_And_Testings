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


?>