(function ($) {
    $(function () {
        const $wooForm = $('form.woocommerce-form.woocommerce-form-login.login');

        if ($wooForm.length) {
            const $bioLoginBtn    = $('.biocryptologylogin-btn').css('margin-bottom', '3px');
            const $wooLoginBtn    = $('button[name=login]').css('margin-bottom', '3px');
            const $wooRegisterBtn = $('button[name=register]').css('margin-bottom', '3px');

            $bioLoginBtn.addClass($wooLoginBtn.attr('class'))
                        .appendTo($wooLoginBtn.parent())
                        .show()
                        .clone()
                        .appendTo($wooRegisterBtn.parent())
                        .show();
        } else {
            const $loginBtn = $('#wp-submit');

            if ($loginBtn.length) {
                const $bioLoginBtn = $('.biocryptologylogin-btn').css('margin-bottom', '3px');

                $bioLoginBtn.addClass($loginBtn.attr('class')).appendTo($loginBtn.parent()).css({
                    'margin-top': '3px',
                    'margin-left': '15px'
                }).show();
            }
        }
    });
})(window.jQuery);
