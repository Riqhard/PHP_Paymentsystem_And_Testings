<?php
  define("COMPANY_CODE", "CompanyCode(WIP)");
  
  define("PAYMENT_SYSTEM_STATEMENT_ERROR", "Error in database statement creation");
  
  // Payment providers
  // EPASSI
  define('PAYMENT_PROVIDER_EPASSI', 'epassi');
  define('EPASSI_API_URL', 'https://prodstaging.Epassi.fi/e_payments/v2');
  define('EPASSI_TESTING', true);


  // MobilePay
  define('PAYMENT_PROVIDER_MOBILEPAY', 'mobilepay');


  
  // Vismapay
  define('PAYMENT_PROVIDER_VISMA', 'vismapay');
  define('VISMA_LIBRARY_LOCATION', '/visma-pay-php-lib/lib/visma_pay_loader.php'); // Visma loader location in Visma Library structure
  //define('VISMA_LIBRARY_LOCATION', '/external/visma_pay_loader.php'); // Visma loader location in Visma Library structure
  //define('VISMA_PATH_LEVELS', '4'); // Number of directories to go up from the current file to find Visma Library installation
  define('VISMA_PATH_LEVELS', '2'); // Number of directories to go up from the current file to find Visma Library installation
  define('VISMA_PRODUCT_TYPE_NORMAL', '1');
  define('VISMA_PRODUCT_TYPE_SHIPMENT', '2');
  define('VISMA_PRODUCT_TYPE_HANDLING', '3');
  define('VISMA_PRODUCT_TYPE_DISCOUNT', '4');
  define('VISMA_PAYMENT_TYPE', 'e-payment');
  define('VISMA_ALLOWED_LANGUAGES', array("fi", "en", "sv", "ru"));
  define('VISMA_DEFAULT_LANGUAGE', "fi");
  define('VISMA_DEFAULT_CURRENCY', "EUR");
  define('VISMA_DEFAULT_TOKEN_VALIDITY', strtotime("+1 hour"));

  define('VISMA_RESULT_SUCCESS', 0);
  define('VISMA_RESULT_VALIDATION_ERROR', 1);
  define('VISMA_RESULT_DUPLICATE_ORDER', 2);
  define('VISMA_RESULT_MAINTANANCE_BREAK', 10);

  define('VISMA_RETURN_SUCCESS', 0);
  define('VISMA_RETURN_FAILED', 1);
  define('VISMA_RETURN_ADDITIONAL_ACTION', 4);
  define('VISMA_RETURN_MAINTANANCE_BREAK', 10);

  define('VISMA_AUTHORIZED', '0');
  define('VISMA_SETTLED', '1');


  // Internal transactions
  define('PAYMENT_PROVIDER_INTERNAL', 'internal');
  define('PAYMENT_PROVIDERS', array(PAYMENT_PROVIDER_EPASSI, PAYMENT_PROVIDER_VISMA, PAYMENT_PROVIDER_INTERNAL));

  // Error states. These might need to be changed to error codes.
  define('ERROR_INVALID_PROVIDER', 'Invalid payment provider');
  define('ERROR_INVALID_PROVIDER_TYPE', 'Invalid payment provider type for transaction');
  define('ERROR_INVALID_ORDER_PRICE', 'Invalid order price');
  define('ERROR_PAYMENT_NOT_FOUND', 'Payment not found');
  define('ERROR_ORDER_NOT_FOUND', 'Order not found');
  define('ERROR_DUPLICATE_PAYMENT', 'Duplicate payment');
  
  define('ERROR_DATABASE_REFERENCE', 'Database reference error');
  define('ERROR_DATABASE_INSERT', 'Database insert error');
  define('ERROR_DATABASE_ERROR', 'Data not found, database error');
  
  define('ERROR_PROVIDER_ERROR', 'Payment provider error');
  
  define('ERROR_INVALID_RESPONSE', 'Invalid response from payment provider');
  define('ERROR_MISMATCHED_TOTAL_PRICE', 'The sum of the cost of order items does not equal given order total cost');
  define('ERROR_VAT_CHECK_ERROR', 'Product ("price without Tax", "VAT percentage" or "price with Tax") calculations do not match');
  define('ERROR_PAYMENT_CANCELLED', 'Payment cancelled or failed.');
  define('ERROR_VISMA_ADDITIONAL_ACTION', 'Manual resolvement needed in Visma merchant UI.');
  define('ERROR_MAINTANANCE_BREAK', 'The website is under maintanance, please try again later.');
  define('ERROR_MOBILEPAY_MISSING_REFERENCE', 'Missing reference in MobilePay response');

  // Payment status
  define('PAYMENT_STATUS_CANCELLED', "cancelled");
  define('PAYMENT_STATUS_PROCESSING', "processing");
  define('PAYMENT_STATUS_AUTHORIZED', "authorized");
  define('PAYMENT_STATUS_COMPLETED', "completed");

  // Payment status codes
  define('PAYMENT_FAILED', '0');
  define('PAYMENT_CANCELLED', '1');
  define('PAYMENT_AUTHORIZED', '2');
  define('PAYMENT_COMPLETED', '3');

  define('ERROR_GENERAL_FAILURE', 'General failure');
?>