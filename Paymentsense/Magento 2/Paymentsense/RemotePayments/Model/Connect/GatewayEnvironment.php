<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\RemotePayments\Model\Connect;

/**
 * Gateway environments
 */
class GatewayEnvironment
{
    public const ENVIRONMENTS = [
        'TEST' => [
            'name'            => 'Test',
            'entry_point_url' => 'https://e.test.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.test.connect.paymentsense.cloud/assets/js/client.js',
        ],
        'PROD' => [
            'name'            => 'Production',
            'entry_point_url' => 'https://e.connect.paymentsense.cloud',
            'client_js_url'   => 'https://web.e.connect.paymentsense.cloud/assets/js/client.js',
        ],
    ];
}
