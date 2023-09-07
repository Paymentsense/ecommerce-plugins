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

<script type="text/javascript">
  const paymentsenserpFormData = {
    {if !$paymentsenserp['error_message']}
    action: '{$paymentsenserp['form_action']|escape:'htmlall':'UTF-8'}',
    params: {
      cartId:       '{$paymentsenserp['form_param_cart_id']|escape:'htmlall':'UTF-8'}',
      amount:       '{$paymentsenserp['form_param_amount']|escape:'htmlall':'UTF-8'}',
      currencyCode: '{$paymentsenserp['form_param_currency_code']|escape:'htmlall':'UTF-8'}',
      paymentToken: '{$paymentsenserp['form_param_payment_token']|escape:'htmlall':'UTF-8'}',
    },
    {/if}
  };
</script>
<script type="text/javascript" src="{$paymentsenserp['paymentsenserp_payment_option_js']|escape:'htmlall':'UTF-8'}"></script>
<div class="payment-method-{$paymentsenserp['module_name']|escape:'htmlall':'UTF-8'}">
  <section>
    <p>
{if $paymentsenserp['module_description']}
      {$paymentsenserp['module_description']|escape:'htmlall':'UTF-8'}
{/if}
    </p>
  </section>
{if $paymentsenserp['error_message']}
  <div class="alert alert-danger">
    {$paymentsenserp['error_message']|escape:'htmlall':'UTF-8'}
  </div>
{/if}
</div>
