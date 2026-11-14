# ArionPay for Blesta

Accept crypto payments (Bitcoin, USDT, Litecoin, Ethereum, TRON and more) in Blesta through [ArionPay](https://arionpay.com). The money goes straight to your own wallet: ArionPay never holds it.

For Blesta 5 with PHP 7.4 or newer (cURL needed). Version 1.0.0.

## Install

1. Unzip `arionpay-blesta-1.0.0.zip` in the Blesta main folder. It adds `components/gateways/nonmerchant/arionpay/`.
2. In Blesta admin, go to *Settings > Company > Payment Gateways > Available*, find **ArionPay (crypto)** and press **Install**.
3. Fill in the settings (see below), tick the currencies you take, and save.

| Setting | Where in the ArionPay dashboard | Starts with |
| --- | --- | --- |
| API key | Open your store, **API key** | `pk_` |
| API secret | Open your store, **API secret** (asks for your authenticator code) | `sk_` |
| Webhook signing secret | **Webhooks** page, **signing secret** | `whsec_` |
| Test mode | Tick it while you try things: no real money moves | |
| ArionPay address | Leave empty | |

Both secrets are stored encrypted by Blesta. Nothing to paste into ArionPay: each invoice tells ArionPay where to send its payment updates. The settings page shows that address (`https://YOUR-BLESTA/callback/gw/1/arionpay/`).

Also check, in your ArionPay store settings, that **Old ArionPay (v1) integration compatibility** is off.

## Try it first

1. With **Test mode** ticked, log in as a client, open an unpaid invoice and pay with **ArionPay (crypto)**.
2. Press **Pay with crypto**, choose a coin on the ArionPay page, then in the ArionPay dashboard open the invoice and use **Try a test payment**. No real money moves.
3. Within a minute the Blesta invoice is paid. The transaction id is `arionpay-` plus the ArionPay invoice id.
4. Untick **Test mode** before you take real payments.

## How payments are recorded

- The client presses **Pay with crypto** and goes to the ArionPay page. Pressing it again opens the same page while it is still open.
- When ArionPay confirms the full payment, Blesta records one approved transaction for the amount that was paid and applies it to the invoices it was for. Repeated updates for the same payment change that one transaction; money is never added twice.
- A payment that is not exact goes to **Payment Radar** in your ArionPay dashboard. A small difference inside your store's payment tolerance (default 1%) is accepted automatically; anything else you decide. If you accept it, the payment is recorded here too.
- A late payment is still recorded.
- "Payment seen", partial, expired and "needs review" updates only go to the gateway log. They don't create a pending transaction.
- If the invoice total changes after the client went to pay, the next press makes a new ArionPay invoice for the new total. If the old one gets paid anyway, Blesta records what was really paid and shows the rest as still due.
- Coming back from the payment page records nothing. Only ArionPay's signed update approves a payment.
- A test invoice is never accepted while Test mode is off, and a real one is never accepted while it is on.

## Good to know

- ArionPay works with these currencies: USD, EUR, GBP, CAD, AUD, CHF, JPY, TRY, AED, INR, BRL, RUB, CNY, SGD, HKD, KRW, MXN, PLN, SEK, NOK, ZAR.
- Automatic recurring charges are not possible with crypto. Clients pay each invoice by hand.
- Refunds are made from your own wallet. Then record the refund in Blesta by hand.
- The gateway sends ArionPay the client number, invoice numbers, amount and currency. It does not send the client's name or email.
- Details of every update are in *Tools > Logs > Gateway*. Keys and secrets are never written there.

## Build from source

The release zip is made with `python3 build.py`. It copies the [ArionPay PHP library](https://github.com/ArionPay-Dev/arionpay-php) into the plugin first, from a folder named `arionpay-php` next to this one:

```bash
git clone https://github.com/ArionPay-Dev/arionpay-php ../arionpay-php
python3 build.py
```
