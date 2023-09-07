<div class="control-group">
    <label class="control-label" for="order_prefix">{__("order_prefix")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][order_prefix]" id="order_prefix" value="{$processor_params.order_prefix}" size="50">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="gateway_username">{__("gateway_username")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][gateway_username]" id="gateway_username" value="{$processor_params.gateway_username}" size="50">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="gateway_jwt">{__("gateway_jwt")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][gateway_jwt]" id="gateway_jwt" value="{$processor_params.gateway_jwt}" size="2000">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="transaction_type">{__("transaction_type")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][transaction_type]" id="transaction_type">
            <option value="SALE" {if $processor_params.transaction_type == "SALE"}selected="selected"{/if}>{__("sale")}</option>
            <option value="PREAUTH" {if $processor_params.transaction_type == "PREAUTH"}selected="selected"{/if}>{__("preauth")}</option>
        </select>
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="gateway_environment">{__("gateway_environment")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][gateway_environment]" id="gateway_environment">
            <option value="TEST" {if $processor_params.gateway_environment == "TEST"}selected="selected"{/if}>{__("test")}</option>
            <option value="PROD" {if $processor_params.gateway_environment == "PROD"}selected="selected"{/if}>{__("production")}</option>
        </select>
    </div>
</div>
