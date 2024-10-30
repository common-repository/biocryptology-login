(function ($) {
    'use strict';

    $(document).ready(function () {
        // For callback url copy to clipboard.
        var clipboard = new Clipboard('.biocryptologyloginclipboardtrigger');
        clipboard.on('success', function (e) {
            e.clearSelection();
        });

        // Select all text on click of callback url text.
        $('.biocryptologyloginclipboard').on("click", function () {
            var $this  = $(this);
            var text   = $this.text();
            var $input = $('<input class="biocryptologyloginclipboard-text" type="text">');

            $input.prop('value', text);
            $input.insertAfter($(this));
            $input.focus();
            $input.select();
            $this.hide();
            $input.focusout(function () {
                $this.show();
                $input.remove();
            });
        });
    });
})(window.jQuery);
