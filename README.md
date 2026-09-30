# instantpay/instantpay-php

Official PHP library for the [InstantPay](https://developers.instantpay.sa) API. PHP 7.4+, needs
only the curl and json extensions. Without Composer, `require 'src/autoload.php'`.

```php
$ip = new \InstantPay\Client(getenv('INSTANTPAY_API_KEY'), [
    'base_url' => 'https://instantpay-api.onrender.com', // demo sandbox; omit for production
]);

$checkout = $ip->paymentIntents->create([
    'amount' => '250.00',
    'description' => 'Order 1001',
    'reference' => '1001',
    'return_url' => 'https://shop.example.sa/orders/1001',
]);
header('Location: ' . $checkout['checkout_url']);

foreach ($ip->payments->all(['state' => 'settled']) as $payment) { /* every page */ }

$ip->refunds->create(['payment_id' => 'pay_…', 'amount' => '20.00', 'reason' => 'One item returned']);

// Webhooks: the raw body, then the event (throws on a bad or old signature)
$event = \InstantPay\Webhook::constructEvent(
    file_get_contents('php://input'),
    $_SERVER['HTTP_INSTANTPAY_SIGNATURE'] ?? null,
    getenv('INSTANTPAY_WEBHOOK_SECRET')
);
```

Errors are `InstantPay\InstantPayException` with `getStatus()`, `getErrorCode()`, `getParam()`
and `getRequestId()`. Options: `base_url`, `timeout` (30), `max_retries` (2), `transport`.
Tests: `php tests/run.php`.
