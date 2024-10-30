<?php
/**
 * Copyright (C) 2018 Hanscan IP B.V.
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Biocryptology <support@biocryptology.com>
 * @copyright 2018 Hanscan IP B.V.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html     GNU General Public License (GPL 3.0)
 */

namespace League\OAuth2\Client\Provider;

class BiocryptologyData {

    const API_HOST                     = 'https://is.biocryptology.com';
    const API_QUICK_CONFIGURATION_HOST = 'https://id.biocryptology.com';
    const POST_LOGIN_URL               = 'wp-login.php?loginbiocryptology=1';
    const RESPONSE_TYPE                = 'code id_token token';
    const SCOPE                        = 'openid profile email address';

    public static function getApiHost() {
        return self::API_HOST;
    }

    public static function getResponseType() {
        return self::RESPONSE_TYPE;
    }

    public static function getApiUrl() {
        return self::API_HOST . '/V1/auth';
    }

    public static function getAutomaticConfigurationApiUrl() {
        return self::API_QUICK_CONFIGURATION_HOST . '/external/cmsinfo';
    }

    public static function getTokenApiUrl() {
        return self::API_HOST . '/V1/token';
    }

    public static function getUserApiUrl() {
        return self::API_HOST . '/V1/userinfo';
    }

    public static function getUserEndApiUrl() {
        return self::API_HOST . '/V1/end';
    }

    public static function getScope() {
        return self::SCOPE;
    }

    public static function getPostLoginUrl() {
        return self::POST_LOGIN_URL;
    }
}
