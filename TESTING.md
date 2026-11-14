# Testing the ArionPay Blesta gateway (about 30 minutes)

Thank you for testing! Everything below uses **test mode**: no real money moves. Please work through the steps in order and note anything that looks different from "Expected". If a menu has a slightly different name in your Blesta version, just use the matching one and tell us.

## What you need

- Blesta 5 on a public `https://` address
- PHP 7.4 or newer with cURL
- An ArionPay account with one store (no wallet needed for test mode)
- A test client in Blesta whose currency is one ArionPay accepts (USD, EUR, GBP, CAD, AUD, CHF, JPY, TRY, AED, INR, BRL, RUB, CNY, SGD, HKD, KRW, MXN, PLN, SEK, NOK or ZAR)

## 1. Install (5 minutes)

1. Unzip `arionpay-blesta-1.0.0.zip` in the Blesta main folder. It adds `components/gateways/nonmerchant/arionpay/`.
2. *Settings > Company > Payment Gateways > Available*: install **ArionPay (crypto)**.
   *Expected:* the ArionPay logo and the settings form.
3. Type something wrong in **API key** (for example `abc`) and save.
   *Expected:* "The API key starts with pk_."
4. Fill in:
   - **API key**: ArionPay dashboard > your store > API key (`pk_...`)
   - **API secret**: ArionPay dashboard > your store > API secret (`sk_...`)
   - **Webhook signing secret**: ArionPay dashboard > Webhooks > signing secret (`whsec_...`)
   - **Test mode**: ticked
   - **ArionPay address**: empty
   - the currencies you use (at least your test client's)
5. Save. *Expected:* saved without errors. The **Payment updates** box shows `https://YOUR-BLESTA/callback/gw/1/arionpay/` (the number is your company id).
6. In ArionPay, open your store settings and check that **Old ArionPay (v1) integration compatibility** is **off**.

## 2. A normal payment

1. Create an invoice for your test client (for example 10.00), unpaid.
2. Log in as the client, open the invoice, press **Pay** and choose **ArionPay (crypto)**.
   *Expected:* the line "Test mode: no real money is taken." and a **Pay with crypto** button.
3. Press **Pay with crypto**. *Expected:* the ArionPay page opens with the right amount and currency.
4. On the ArionPay page, choose a coin (for example USDT TRC20).
5. In another tab, open the ArionPay dashboard > **Invoices**, open this test invoice, and in **Try a test payment** press **Customer pays exactly**.
6. Go back to Blesta. Within a minute: *Expected:*
   - the Blesta invoice is **paid**
   - one transaction, approved, with transaction id `arionpay-` plus the ArionPay invoice id
   - *Tools > Logs > Gateway* has entries for ArionPay, with no keys in them
7. Press **Return to merchant** (or similar) on the ArionPay page. *Expected:* Blesta's "payment received" page, and still only one transaction.

## 3. Pressing Pay twice

1. Make another unpaid invoice, press **Pay with crypto**, close the ArionPay page, go back and press **Pay with crypto** again.
   *Expected:* the **same** ArionPay page opens (same address).

## 4. Wrong amounts (Payment Radar)

1. New invoice, **Pay with crypto**, choose a coin, then in the dashboard press **Customer pays 5% less**.
   *Expected:* the Blesta invoice stays **unpaid** and no transaction is added. In ArionPay the payment shows in **Payment Radar** for you to decide.
2. New invoice, same steps, press **Customer pays 1% less**.
   *Expected:* the invoice is **paid** in full (inside the default 1% tolerance).
3. New invoice, same steps, press **Customer pays too late**.
   *Expected:* **paid**.

## 5. Several invoices at once, and account credit

1. Make two unpaid invoices for the client. As the client, pay both together (select both, then pay with ArionPay) and pay exactly in the dashboard.
   *Expected:* both invoices **paid**, by one transaction.
2. If you allow clients to add credit: add credit (for example 5.00) with ArionPay and pay exactly.
   *Expected:* the client's credit goes up by 5.00.

## 6. Invoice changed after the client went to pay

1. New invoice for 10.00, **Pay with crypto**, choose a coin (do not pay yet).
2. In Blesta admin, edit the invoice: add a line so the total becomes 12.00.
3. As the client, press **Pay with crypto** again. *Expected:* a **new** ArionPay page for 12.00.
4. In the dashboard, press **Customer pays exactly** on the **old** (10.00) ArionPay invoice.
   *Expected:* a 10.00 transaction is recorded, and the invoice shows 2.00 still due.

## 7. Webhook checks

1. ArionPay dashboard > **Webhooks**: paste the address from the **Payment updates** box, then press **Send test webhook**.
   *Expected:* "delivered". Nothing changes in Blesta; the gateway log has an entry for the test.
2. In Blesta, change the **Webhook signing secret** to something wrong (it must still start with `whsec_`), save, and press **Send test webhook** again.
   *Expected:* not delivered (401). **Put the right secret back.**

## 8. Optional: one real payment

Untick **Test mode**, connect a real wallet in ArionPay, and pay a small invoice (for example 2.00 in USDT on TRON). *Expected:* the same as step 2, without the test-mode line.

## What to send back

For each step: OK, or what happened instead. If something went wrong, please include:

- Blesta version, PHP version, and the gateway version (1.0.0)
- the matching entries from *Tools > Logs > Gateway* (they never contain keys)
- anything in Blesta's error log, or the PHP error log, from that time
- a screenshot of the page with the problem
- the ArionPay invoice id (shown in the payment page address after `/pay/`)
