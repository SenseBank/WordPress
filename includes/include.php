<?php

define('PAYMENT_SENSEBANK_PAYMENT_NAME', 'Sense Bank UA');

define('PAYMENT_SENSEBANK_PROD_URL' , 'https://pay.sensebank.com.ua/ml/v1/payment/'); //production
define('PAYMENT_SENSEBANK_TEST_URL' , 'https://sand.sensebank.com.ua/ml/v1/payment/'); //test/ sandbox URL

define('PAYMENT_SENSEBANK_ENABLE_LOGGING', true);
define('PAYMENT_SENSEBANK_ENABLE_CART_OPTIONS', false);

define('PAYMENT_SENSEBANK_MEASUREMENT_NAME', 'шт'); //FFD v1.05
define('PAYMENT_SENSEBANK_MEASUREMENT_CODE', 0); //FFD v1.2

define('PAYMENT_SENSEBANK_SKIP_CONFIRMATION_STEP', true);
define('PAYMENT_SENSEBANK_CUSTOMER_EMAIL_SEND', true); //PLUG-4667
define('PAYMENT_SENSEBANK_ENABLE_CALLBACK', false);

define('PAYMENT_SENSEBANK_DB_TRANSACTIONS', 'sensebank_transactions');

define('PAYMENT_SENSEBANK_MAX_REPEAT_ORDER_STATUS_CHECK_IF_SYSTEM_ERROR_CODE', 10);

define('PAYMENT_SENSEBANK_CURRENCY_CODES', serialize(array(
    'USD' => '840',
    'UAH' => '980',
    'RON' => '946',
    'KZT' => '398',
    'KGS' => '417',
    'JPY' => '392',
    'GBR' => '826',
    'EUR' => '978',
    'CNY' => '156',
    'BYR' => '974',
    'BYN' => '933'
)));