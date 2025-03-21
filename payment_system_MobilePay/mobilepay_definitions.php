<?php
define('MOBILEPAY_API_URL', 'https://api.vipps.no/epayment/v1/payments');
define('MOBILEPAY_APITEST_URL', 'https://apitest.vipps.no/epayment/v1/payments');

define('MOBILEPAY_USERFLOW', 'WEB-REDIRECT');
define('MOBILEPAY_PAYMENT_METHOD', 'WALLET');
define('MOBILEPAY_DEFAULT_CURRENCY', 'EUR');

// Endpoints
define('MOBILEPAY_CREATE', 'create'); // POST, requires idempotency key, merchant serial number, subscription key
define('MOBILEPAY_QUERY', 'query'); // GET, requires merchant serial number, subscription key, reference ID
define('MOBILEPAY_CANCEL', 'cancel'); // POST, requires merchant serial number, subscription key, reference ID
define('MOBILEPAY_ADJUST', 'adjust'); // POST, requires merchant serial number, subscription key, reference ID, IDempotency key
define('MOBILEPAY_FORCE_APPROVE', 'forceApprove'); // POST, Requires merchant serial number, subscription key, reference ID

define('MOBILEPAY_STATE_CREATED', 'CREATED');
define('MOBILEPAY_STATE_ABORTED', 'ABORTED');
define('MOBILEPAY_STATE_EXPIRED', 'EXPIRED');
define('MOBILEPAY_STATE_AUTHORIZED', 'AUTHORIZED');
define('MOBILEPAY_STATE_TERMINATED', 'TERMINATED');
?>