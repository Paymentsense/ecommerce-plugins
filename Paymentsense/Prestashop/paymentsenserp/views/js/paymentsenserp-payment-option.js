/**
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
 */

function setPaymentsenserpPaymentData(form)
{
    let result = false;
    if (Object.prototype.hasOwnProperty.call(paymentsenserpFormData, 'action')) {
        form.action = decodeUrl(paymentsenserpFormData.action);
        form.method = "POST";
        for (let prop in paymentsenserpFormData.params) {
            if (!paymentsenserpFormData.params.hasOwnProperty(prop)) {
                continue;
            }
            let element = document.createElement("input");
            element.name = prop;
            element.value = paymentsenserpFormData.params[prop];
            element.type = "hidden";
            form.appendChild(element);
        }
        result = true;
    }
    return result;
}

function decodeUrl(url)
{
    let textarea = document.createElement('textarea');
    textarea.innerHTML = url;
    return textarea.value;
}
