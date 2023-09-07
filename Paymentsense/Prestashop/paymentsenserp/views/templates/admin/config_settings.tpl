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

{if $notice_text != ""}
    <div class="alert alert-{$notice_type|escape:'htmlall':'UTF-8'}">
        <p>{$notice_text|escape:'htmlall':'UTF-8'}</p>
    </div>
{/if}
<div class="alert alert-info">
    <img src="{$module_path|escape:'htmlall':'UTF-8'}views/img/logo.png" style="float:left; margin-right:15px;" height="36" alt="Paymentsense Logo">
    <p><strong>{l s='This module allows you to accept payments via Paymentsense Remote Payments.' mod='paymentsenserp'}</strong></p>
    <p>{l s='Please enter your gateway details below and click save.' mod='paymentsenserp'}</p>
</div>
<form id="configuration_form" class="defaultForm form-horizontal" action="{$form_action|escape:'htmlall':'UTF-8'}" method="post">
    <input type="hidden" name="btnSubmit" value="1" />
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">
            <i class="icon-edit"></i> {l s='Configuration settings' mod='paymentsenserp'}
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                <label for="PAYMENTSENSERP_MODULE_TITLE" class="control-label col-lg-3 required">
                    {l s='Title: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <input name="PAYMENTSENSERP_MODULE_TITLE" id="PAYMENTSENSERP_MODULE_TITLE" type="text" required="required"
                           value="{$form_var_module_title|escape:'htmlall':'UTF-8'}" />
                    <p class="help-block">
                        {l s='This controls the title which the customer sees during checkout.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_MODULE_DESCRIPTION" class="control-label col-lg-3 required">
                    {l s='Description: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <input name="PAYMENTSENSERP_MODULE_DESCRIPTION" id="PAYMENTSENSERP_MODULE_DESCRIPTION" type="text" required="required"
                           value="{$form_var_module_description|escape:'htmlall':'UTF-8'}" />
                    <p class="help-block">
                        {l s='This controls the description which the customer sees during checkout.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_ORDER_PREFIX" class="control-label col-lg-3 required">
                    {l s='Order Prefix: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <input name="PAYMENTSENSERP_ORDER_PREFIX" id="PAYMENTSENSERP_ORDER_PREFIX" type="text" required="required"
                           value="{$form_var_order_prefix|escape:'htmlall':'UTF-8'}" />
                    <p class="help-block">
                        {l s='This is the order prefix that you will see in the Merchant Portal.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_GATEWAY_USERNAME" class="control-label col-lg-3 required">
                    {l s='Gateway Username/URL: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <input name="PAYMENTSENSERP_GATEWAY_USERNAME" id="PAYMENTSENSERP_GATEWAY_USERNAME" type="text" required="required"
                           value="{$form_var_gateway_username|escape:'htmlall':'UTF-8'}" />
                    <p class="help-block">
                        {l s='This is the gateway username or URL.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_GATEWAY_JWT" class="control-label col-lg-3 required">
                    {l s='Gateway JWT: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <input name="PAYMENTSENSERP_GATEWAY_JWT" id="PAYMENTSENSERP_GATEWAY_JWT" type="text" required="required"
                           value="{$form_var_gateway_jwt|escape:'htmlall':'UTF-8'}" />
                    <p class="help-block">
                        {l s='This is the gateway JWT.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_TRANSACTION_TYPE" class="control-label col-lg-3 required">
                    {l s='Transaction Type: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <select name="PAYMENTSENSERP_TRANSACTION_TYPE" id="PAYMENTSENSERP_TRANSACTION_TYPE">
                        <option value="SALE"{if $form_var_trx_type == "SALE"} selected="selected"{/if}>{l s='SALE' mod='paymentsenserp'}</option>
                        <option value="PREAUTH"{if $form_var_trx_type == "PREAUTH"} selected="selected"{/if}>{l s='PREAUTH' mod='paymentsenserp'}</option>
                    </select>
                    <p class="help-block">
                        {l s='If you wish to obtain authorisation for the payment only, as you intend to manually collect the payment via the Merchant Portal, choose Pre-auth.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label for="PAYMENTSENSERP_GATEWAY_ENVIRONMENT" class="control-label col-lg-3 required">
                    {l s='Gateway Environment: ' mod='paymentsenserp'}
                </label>
                <div class="col-lg-9">
                    <select name="PAYMENTSENSERP_GATEWAY_ENVIRONMENT" id="PAYMENTSENSERP_GATEWAY_ENVIRONMENT">
                        <option value="TEST"{if $form_var_gateway_environment == "TEST"} selected="selected"{/if}>{l s='Test' mod='paymentsenserp'}</option>
                        <option value="PROD"{if $form_var_gateway_environment == "PROD"} selected="selected"{/if}>{l s='Production' mod='paymentsenserp'}</option>
                    </select>
                    <p class="help-block">
                        {l s='Gateway environment for performing transactions.' mod='paymentsenserp'}
                    </p>
                </div>
            </div>
        </div>
        <div class="panel-footer">
            <button type="submit" value="1"	id="configuration_form_submit_btn" name="btnSubmit" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>{l s='Save' mod='paymentsenserp'}
            </button>
        </div>
    </div>
</form>
