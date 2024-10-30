(function ($) {
    'use strict';

    $(document).ready(function () {
        var domain = window.location.protocol + '\//'
            + window.location.hostname
            + (window.location.port ? ':' + window.location.port : '');

        $.validator.addMethod('regex', function (value, element, regexp) {
            if (regexp.constructor !== RegExp) {
                regexp = new RegExp(regexp);
            } else if (regexp.global) {
                regexp.lastIndex = 0;
            }

            return this.optional(element) || regexp.test(value);
        }, 'Please enter a valid URL.');

        $('#biocryptologylogin_api_config').find('form').validate({
            errorClass: 'biocryptologylogin-config-error',
            rules     : {
                'biocryptologylogin_api_config[client_id]'        : {
                    required  : true,
                    normalizer: function (value) {
                        return $.trim(value);
                    }
                },
                'biocryptologylogin_api_config[secret_key]'       : {
                    required  : true,
                    normalizer: function (value) {
                        return $.trim(value);
                    }
                },
                'biocryptologylogin_api_config[user_creation_url]': {
                    regex: '^' + domain + '\/'
                }
            },
            messages  : {
                'biocryptologylogin_api_config[client_id]'        : '',
                'biocryptologylogin_api_config[secret_key]'       : '',
                'biocryptologylogin_api_config[user_creation_url]': ''
            }
        });
    });
})(window.jQuery);
