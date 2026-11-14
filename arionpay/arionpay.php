<?php
/**
 * ArionPay for Blesta: crypto payments (Bitcoin, USDT, Litecoin, Ethereum, TRON and more) paid straight to the
 * merchant's own wallet.
 *
 * The client presses "Pay with crypto" and goes to the ArionPay payment page. Only a signed ArionPay webhook approves the
 * payment. The ArionPay invoice id is the transaction id, so
 * repeated updates change one transaction instead of adding money twice. ArionPay alone decides whether an invoice
 * is paid; this gateway never compares crypto amounts.
 *
 * Install: copy this folder to components/gateways/nonmerchant/arionpay/.
 *
 * @license MIT
 */
if (!class_exists('\ArionPay\Client', true)) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'arionpay-php' . DIRECTORY_SEPARATOR . 'autoload.php';
}

class Arionpay extends NonmerchantGateway
{
    /** @var array Gateway settings */
    private $meta;

    /** @var string Currency of the current payment */
    private $currency;

    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');
        Loader::loadComponents($this, ['Input']);
        Language::loadLang('arionpay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    public function setCurrency($currency)
    {
        $this->currency = strtoupper((string) $currency);
    }

    public function getSettings(?array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Form', 'Html']);
        $this->view->set('meta', $meta);
        $this->view->set('webhook_url', $this->webhookUrl());
        return $this->view->fetch();
    }

    public function editSettings(array $meta)
    {
        $rules = [
            'api_key' => [
                'format' => [
                    'rule' => ['matches', '/^pk_[A-Za-z0-9_-]{8,64}$/'],
                    'message' => Language::_('Arionpay.!error.api_key.format', true),
                ],
            ],
            'api_secret' => [
                'format' => [
                    'rule' => ['matches', '/^sk_\S+$/'],
                    'message' => Language::_('Arionpay.!error.api_secret.format', true),
                ],
            ],
            'webhook_secret' => [
                'format' => [
                    'rule' => ['matches', '/^whsec_\S+$/'],
                    'message' => Language::_('Arionpay.!error.webhook_secret.format', true),
                ],
            ],
        ];
        if (!isset($meta['test_mode'])) {
            $meta['test_mode'] = 'false';
        }
        $meta['base_url'] = rtrim(trim(isset($meta['base_url']) ? (string) $meta['base_url'] : ''), '/');
        $this->Input->setRules($rules);
        $this->Input->validates($meta);
        return $meta;
    }

    public function encryptableFields()
    {
        return ['api_secret', 'webhook_secret'];
    }

    public function setMeta(?array $meta = null)
    {
        $this->meta = (array) $meta;
    }

    /**
     * The pay step: make (or reuse) the ArionPay invoice for this payment and show a button to its payment page.
     */
    public function buildProcess(array $contactInfo, $amount, ?array $invoiceAmounts = null, ?array $options = null)
    {
        $clientId = isset($contactInfo['client_id']) ? (int) $contactInfo['client_id'] : 0;
        $currency = strtoupper((string) $this->currency);
        $amount = number_format((float) $amount, 2, '.', '');
        $invoices = $this->serializeInvoices((array) $invoiceAmounts);
        $returnUrl = isset($options['return_url']) ? (string) $options['return_url'] : '';
        $test = $this->isTestMode();

        try {
            $url = $this->paymentUrl($clientId, $amount, $currency, $invoices, $returnUrl, $test, $options);
        } catch (\Throwable $e) {
            $this->log('arionpay', json_encode(['client_id' => $clientId, 'amount' => $amount, 'currency' => $currency, 'error' => $e->getMessage()]), 'output', false);
            $this->Input->setErrors(['arionpay' => ['create' => Language::_('Arionpay.!error.create', true)]]);
            return '';
        }

        if ($url === null) {
            $this->log('arionpay', json_encode(['client_id' => $clientId, 'amount' => $amount, 'currency' => $currency, 'note' => 'ArionPay already shows this payment as paid; no new invoice made']), 'output', true);
            return '<p>' . htmlspecialchars(Language::_('Arionpay.process.already_paid', true), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $html = '<p>' . htmlspecialchars(Language::_('Arionpay.process.text', true), ENT_QUOTES, 'UTF-8') . '</p>';
        if ($test) {
            $html .= '<p><strong>' . htmlspecialchars(Language::_('Arionpay.process.test', true), ENT_QUOTES, 'UTF-8') . '</strong></p>';
        }
        $html .= '<a class="btn btn-primary" id="arionpay-pay" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Language::_('Arionpay.process.button', true), ENT_QUOTES, 'UTF-8') . '</a>';
        return $html;
    }

    /**
     * Webhook from ArionPay (POST /callback/gw/<company>/arionpay/). Returns transaction data for Blesta, or nothing
     * when the request changes no payment.
     */
    public function validate(array $get, array $post)
    {
        $secret = $this->metaValue('webhook_secret');
        $raw = (string) file_get_contents('php://input', false, null, 0, 262145);
        if ($secret === '') {
            $this->respondStatus(503);
            return null;
        }
        if (strlen($raw) > 262144) {
            $this->respondStatus(413);
            return null;
        }
        $header = isset($_SERVER['HTTP_ARIONPAY_SIGNATURE']) ? (string) $_SERVER['HTTP_ARIONPAY_SIGNATURE'] : '';
        try {
            $event = \ArionPay\Webhook::verify($raw, $header, $secret);
        } catch (\ArionPay\SignatureException $e) {
            $this->log('arionpay', json_encode(['error' => $e->getMessage()]), 'input', false);
            $this->Input->setErrors(['signature' => ['invalid' => Language::_('Arionpay.!error.signature', true)]]);
            $this->respondStatus(401);
            return null;
        }
        $this->log('arionpay', json_encode($this->logFields($event['data']['invoice'], $event['type'])), 'input', true);

        if (\ArionPay\Webhook::isTestEvent($event) || !in_array($event['type'], \ArionPay\Webhook::EVENT_TYPES, true)) {
            return null; // "Send test webhook" events describe a made-up invoice; unknown types change nothing
        }
        return $this->transactionFor($event['type'], $event['data']['invoice'], true);
    }

    /**
     * The client came back from the payment page. That proves nothing, so nothing is recorded here (no transaction
     * id): Blesta shows the payment as received and pending, and only ArionPay's signed webhook (validate()) approves it.
     */
    public function success(array $get, array $post)
    {
        return ['status' => 'pending'];
    }

    public function capture($referenceId, $transactionId, $amount, ?array $invoiceAmounts = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    public function void($referenceId, $transactionId, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    public function refund($referenceId, $transactionId, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    // ------------------------------------------------------------------ internals

    /**
     * Turn a verified ArionPay invoice into a Blesta transaction, or null when it must not change anything.
     *
     * @param array $remote invoice from a verified webhook
     */
    private function transactionFor($type, array $remote, $fromWebhook)
    {
        $metadata = isset($remote['metadata']) && is_array($remote['metadata']) ? $remote['metadata'] : [];
        if (!isset($metadata['blesta_client_id'], $metadata['blesta_amount'], $metadata['blesta_currency'])) {
            return null; // made in the ArionPay dashboard, a payment link or another system on the same store
        }
        if ((bool) (isset($remote['test']) ? $remote['test'] : false) !== $this->isTestMode()) {
            $this->log('arionpay', json_encode(['invoice' => $remote['id'] ?? '', 'error' => 'test/live mode differs from this gateway']), 'input', false);
            if ($fromWebhook) {
                $this->respondStatus(409);
            }
            return null;
        }
        $amount = (string) $metadata['blesta_amount'];
        $currency = strtoupper((string) $metadata['blesta_currency']);
        $paid = isset($remote['amountFiat']) && is_numeric($remote['amountFiat']) ? number_format((float) $remote['amountFiat'], 2, '.', '') : '';

        if ($type !== 'invoice.paid') {
            // Payment seen, partial, expired, needs review: logged only. No pending transaction is recorded, because a
            // retried "pending" update arriving after "paid" would set the approved payment back to pending.
            return null;
        }
        // The paid ArionPay invoice must be exactly the one this gateway made (amount and currency).
        if ($paid !== $amount || strtoupper((string) ($remote['currency'] ?? '')) !== $currency) {
            $this->log('arionpay', json_encode(['invoice' => $remote['id'] ?? '', 'error' => 'paid amount or currency does not match the payment that was started; not approved']), 'input', false);
            return null;
        }

        return [
            'client_id' => (int) $metadata['blesta_client_id'],
            'amount' => $amount,
            'currency' => $currency,
            'invoices' => $this->unserializeInvoices(isset($metadata['blesta_invoices']) ? (string) $metadata['blesta_invoices'] : ''),
            'status' => 'approved',
            'reference_id' => null,
            'transaction_id' => 'arionpay-' . (string) ($remote['id'] ?? ''),
            'parent_transaction_id' => null,
        ];
    }

    /**
     * The ArionPay invoice for this payment: the open one when it still fits, otherwise a new one (stateless).
     *
     * @return string|null payment page address, or null when ArionPay already shows this payment as paid
     */
    private function paymentUrl($clientId, $amount, $currency, $invoices, $returnUrl, $test, $options)
    {
        $site = substr(hash('sha256', (string) Configure::get('Blesta.gw_callback_url') . '|' . Configure::get('Blesta.company_id')), 0, 12);
        $base = 'blesta-' . $site . '-' . $clientId . '-' . substr(hash('sha256', $invoices), 0, 12) . '-' . str_replace('.', '', $amount) . $currency . ($test ? '-test' : '');
        $client = $this->client();
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $created = $client->createInvoice([
                'amount' => $amount,
                'currency' => $currency,
                'orderId' => 'C' . $clientId . '-' . ($invoices !== '' ? strtok($invoices, '=') : 'credit'),
                'description' => $this->cut(isset($options['description']) && $options['description'] !== '' ? (string) $options['description'] : 'Payment from client #' . $clientId, 500),
                'successUrl' => $returnUrl,
                'cancelUrl' => $returnUrl,
                'callbackUrl' => $this->webhookUrl(),
                'metadata' => [
                    'blesta_client_id' => (int) $clientId,
                    'blesta_invoices' => $invoices,
                    'blesta_amount' => $amount,
                    'blesta_currency' => $currency,
                    'platform' => 'blesta',
                ],
                'idempotencyKey' => $base . '-' . $attempt,
                'test' => $test,
            ]);
            $expires = isset($created['expiresAt']) ? strtotime((string) $created['expiresAt']) : 0;
            $status = isset($created['status']) ? $created['status'] : 'new';
            if (($status === 'new' && $expires > time() + 300) || in_array($status, ['pending', 'paid_partial', 'claim_pending'], true)) {
                return $created['invoiceUrl'];
            }
            if ($status === 'paid') {
                return null; // this exact payment is already paid: never offer a second invoice for it
            }
        }
        throw new \Exception('Too many ArionPay invoices for this payment.');
    }

    /** Where ArionPay sends this payment's updates (Blesta's gateway callback address). */
    private function webhookUrl()
    {
        return Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/arionpay/';
    }

    private function client()
    {
        $base = $this->metaValue('base_url');
        return new \ArionPay\Client($this->metaValue('api_key'), $this->metaValue('api_secret'), [
            'baseUrl' => $base !== '' ? $base : 'https://merchant.arionpay.com',
            'appInfo' => 'blesta' . (defined('BLESTA_VERSION') ? '/' . BLESTA_VERSION : '') . ' arionpay-blesta/' . $this->getVersion(),
        ]);
    }

    private function isTestMode()
    {
        return $this->metaValue('test_mode') === 'true';
    }

    private function metaValue($key)
    {
        return isset($this->meta[$key]) ? trim((string) $this->meta[$key]) : '';
    }

    private function serializeInvoices(array $invoiceAmounts)
    {
        $parts = [];
        foreach ($invoiceAmounts as $invoice) {
            if (isset($invoice['id'], $invoice['amount'])) {
                $parts[] = (int) $invoice['id'] . '=' . number_format((float) $invoice['amount'], 2, '.', '');
            }
        }
        return implode('|', $parts);
    }

    private function unserializeInvoices($str)
    {
        $invoices = [];
        foreach (array_filter(explode('|', (string) $str)) as $pair) {
            $p = explode('=', $pair, 2);
            if (count($p) === 2 && ctype_digit($p[0]) && is_numeric($p[1])) {
                $invoices[] = ['id' => (int) $p[0], 'amount' => $p[1]];
            }
        }
        return $invoices;
    }

    private function logFields(array $remote, $type)
    {
        $out = ['event' => $type];
        foreach (['id', 'test', 'orderId', 'status', 'amountFiat', 'currency', 'asset', 'network', 'paidCrypto', 'late', 'manual', 'overpaid', 'underpaidWithinTolerance', 'needsReview', 'reviewReason'] as $k) {
            if (array_key_exists($k, $remote)) {
                $out[$k] = $remote[$k];
            }
        }
        return $out;
    }

    private function cut($text, $length)
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
    }

    private function respondStatus($code)
    {
        if (!headers_sent()) {
            http_response_code((int) $code);
        }
    }
}
