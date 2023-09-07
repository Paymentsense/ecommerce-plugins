{**
 * Copyright (C) 2021 Paymentsense Ltd.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms
 * of the AFL Academic Free License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the AFL Academic Free License for more details. You should have received a copy of the
 * AFL Academic Free License along with this program. If not, see <http://opensource.org/licenses/AFL-3.0/>.
 *
 *  @author     Paymentsense <devsupport@paymentsense.com>
 *  @copyright  2021 Paymentsense Ltd.
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}

{extends file='page.tpl'}
{block name="page_content_container"}
    <script type="text/javascript">
        const paymentsenserpConfig = {
            paymentDetails: {
                amount:       '{$paymentsenserp['payment_details_amount']|escape:'htmlall':'UTF-8'}',
                currencyCode: '{$paymentsenserp['payment_details_currency_code']|escape:'htmlall':'UTF-8'}',
                paymentToken: '{$paymentsenserp['payment_details_payment_token']|escape:'htmlall':'UTF-8'}',
            },
            cartId:    '{$paymentsenserp['cart_id']|escape:'htmlall':'UTF-8'}',
            returnUrl: '{$paymentsenserp['return_url']|escape:'htmlall':'UTF-8'}',
        };
    </script>
    <section id="content" class="page-content card card-block">
        {block name='page_content_top'}{/block}
        {block name="page_content"}
            <h1 id="paymentsenserp_title">{$paymentsenserp['title']|escape:'htmlall':'UTF-8'}</h1>
            <div class="content">
                <p id="paymentsenserp_description">{$paymentsenserp['message']|escape:'htmlall':'UTF-8'}</p>
                <div id="paymentsenserp-payment-div"></div>
                <div id="paymentsenserp-errors-div"></div>
                <div id="paymentsenserp-button-div"><button id="paymentsenserp-submit-payment-btn" class="btn btn-primary"></button></div>
            </div>
        {/block}
    </section>
    <section id="paymentsenserp-links">
        <a class="label" href="{$paymentsenserp['checkout_url']|escape:'htmlall':'UTF-8'}" title="{l s='Go back to the Checkout' mod='paymentsenserp'}">
            <i id="paymentsenserp-links-chevron" class="material-icons">chevron_left</i>{l s='Go back to the Checkout' mod='paymentsenserp'}
        </a>
    </section>
{/block}
