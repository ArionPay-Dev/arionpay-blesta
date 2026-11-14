<?php
$lang['Arionpay.name'] = 'ArionPay (crypto)';
$lang['Arionpay.description'] = 'Accept Bitcoin, USDT, Litecoin, Ethereum, TRON and more. The money goes straight to your own wallet: ArionPay never holds it.';

$lang['Arionpay.api_key'] = 'API key';
$lang['Arionpay.api_key_note'] = 'ArionPay dashboard > your store > API key (starts with pk_).';
$lang['Arionpay.api_secret'] = 'API secret';
$lang['Arionpay.api_secret_note'] = 'ArionPay dashboard > your store > API secret (starts with sk_). Stored encrypted.';
$lang['Arionpay.webhook_secret'] = 'Webhook signing secret';
$lang['Arionpay.webhook_secret_note'] = 'ArionPay dashboard > Webhooks > signing secret (starts with whsec_). Stored encrypted.';
$lang['Arionpay.test_mode'] = 'Test mode';
$lang['Arionpay.test_mode_note'] = 'Test invoices: no real money moves. Turn it off before you take real payments.';
$lang['Arionpay.base_url'] = 'ArionPay address';
$lang['Arionpay.base_url_note'] = 'Leave empty unless ArionPay tells you otherwise.';
$lang['Arionpay.webhook_url'] = 'Payment updates';
$lang['Arionpay.webhook_url_note'] = 'Nothing to paste: every invoice tells ArionPay to send its updates here. You can also paste it on the ArionPay Webhooks page to use "Send test webhook".';

$lang['Arionpay.process.text'] = 'You will go to the secure ArionPay page to choose a coin and pay. Your payment is recorded as soon as the network confirms it.';
$lang['Arionpay.process.test'] = 'Test mode: no real money is taken.';
$lang['Arionpay.process.button'] = 'Pay with crypto';
$lang['Arionpay.process.already_paid'] = 'ArionPay already has this payment. It will show on your invoice in a moment.';

$lang['Arionpay.!error.api_key.format'] = 'The API key starts with pk_.';
$lang['Arionpay.!error.api_secret.format'] = 'The API secret starts with sk_.';
$lang['Arionpay.!error.webhook_secret.format'] = 'The webhook signing secret starts with whsec_.';
$lang['Arionpay.!error.create'] = 'The crypto payment could not be started right now. Please try again in a minute.';
$lang['Arionpay.!error.signature'] = 'The ArionPay webhook signature is not valid.';
