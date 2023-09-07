<?php
/**
 * Paymentsense Remote Payments
 *
 * Web:   https://www.paymentsense.com/
 * License:  GPL-3.0 https://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<form action="{$VAL_SELF}" method="post" enctype="multipart/form-data">
	<div id="paymentsense_remotepayments" class="tab_content">
		<h3>{$LANG.paymentsense_remotepayments.module_title}</h3>
		<p><b>Module Version:</b> {$LANG.paymentsense_remotepayments.module_version}<br/><b>Release
				Date:</b> {$LANG.paymentsense_remotepayments.module_date}</p>
		<p><a href="http://www.paymentsense.com/" target="_blank">Paymentsense Website</a><br/>
		<fieldset>
			<legend>{$LANG.module.cubecart_settings}</legend>
			<div>
				<label for="status">{$LANG.common.status}</label>
				<span>
				<input type="hidden" name="module[status]" id="status" class="toggle" value="{$MODULE.status}"/>
			</span></div>
			<div><label for="position">{$LANG.module.position}</label><span><input type="text" name="module[position]"
																				   id="position" class="textbox number"
																				   value="{$MODULE.position}"/></span>
			</div>
			<div>
				<label for="description">{$LANG.common.description}</label>
				<span>
				<input name="module[desc]" id="description" class="textbox" type="text" value="{$MODULE.desc}"/>
			</span></div>
		</fieldset>
		<fieldset>
			<legend>Paymentsense - Remote Payments Gateway Settings</legend>
			<p><em>{$LANG.paymentsense_remotepayments.merchantsettings_description}</em></p>
			<div>
				<label for="gatewayUsername">{$LANG.paymentsense_remotepayments.merchantsettings_gateway_username}</label>
				<span>
				<input name="module[gatewayUsername]" id="gatewayUsername" class="textbox" type="text"
					   value="{$MODULE.gatewayUsername}"/>
			</span></div>
			<div>
				<label for="gatewayJwt">{$LANG.paymentsense_remotepayments.merchantsettings_gateway_jwt}</label>
				<span>
				<input name="module[gatewayJwt]" id="gatewayJwt" class="textbox" type="text"
					   value="{$MODULE.gatewayJwt}"/>
			</span></div>
			<div>
				<label for="module[environment]">{$LANG.paymentsense_remotepayments.merchantsettings_environment}</label>
				<select name="module[environment]">
					<option value="Production" {$SELECT_environment_Production}>{$LANG.paymentsense_remotepayments.merchantsettings_env_production}</option>
					<option value="Test" {$SELECT_environment_Test}>{$LANG.paymentsense_remotepayments.merchantsettings_env_test}</option>
				</select>
			</div>
			<div>
				<label for="orderPrefix">{$LANG.paymentsense_remotepayments.merchantsettings_order_prefix}</label>
				<span>
				<input name="module[orderPrefix]" id="orderPrefix" class="textbox" type="text"
					   value="{$MODULE.orderPrefix}"/>
			</span>
			</div>
		</fieldset>
	</div>
    {$MODULE_ZONES}
	<div class="form_control">
		<input type="submit" name="save" value="{$LANG.common.save}"/>
	</div>
	<input type="hidden" name="token" value="{$SESSION_TOKEN}"/>
</form>
