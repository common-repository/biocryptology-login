(function ($) {
    function hideElement(element, parent) {
        const $element   = $(`[name="biocryptologylogin_api_config\\[${element}\\]"]`);
        const realParent = parent.toLowerCase();

        if ($element.length) {
            $element.parents(realParent).toggle(false);
        }
    }

    function showElement(element, parent) {
        const $element   = $(`[name="biocryptologylogin_api_config\\[${element}\\]"]`);
        const realParent = parent.toLowerCase();

        if ($element.length) {
            $element.parents(realParent).toggle(true);
        }
    }

    function hideSubmitButton() {
        const $button = $('#submit');

        if ($button.length) {
            $button.parents('p.submit').parent('div').toggle(false);
        }
    }

    function showSubmitButton() {
        const $button = $('#submit');

        if ($button.length) {
            $button.parents('p.submit').parent('div').toggle(true);
        }
    }

    const configurations = {
        0() { // Default.
            hideElement('client_id', 'tr');
            hideElement('secret_key', 'tr');
            hideElement('domain_code', 'tr');
            hideElement('post_login_url', 'tr');
            hideElement('user_autocreation', 'tr');
            hideElement('user_creation_url', 'tr');
            hideElement('claims', 'tr');
            hideElement('delete_global_config', 'tr');
            hideElement('configuration_generation_button', 'tr');
            hideElement('configuration_generation_button', 'p');
            hideElement('glosary', 'tr');
            hideElement('identity_server_url', 'tr');
            hideElement('basic_configuration_text', 'p');
            hideElement('configuration_importation_button', 'p');
            hideElement('configuration_importation_back_button', 'p');
            hideSubmitButton();
        },
        // Quick setup.
        1() {
            hideElement('client_id', 'tr');
            hideElement('secret_key', 'tr');
            hideElement('domain_code', 'tr');
            hideElement('post_login_url', 'tr');
            hideElement('user_autocreation', 'tr');
            hideElement('user_creation_url', 'tr');
            hideElement('claims', 'tr');
            hideElement('delete_global_config', 'tr');
            showElement('configuration_generation_button', 'tr');
            showElement('configuration_generation_button', 'p');
            hideElement('glosary', 'tr');
            hideElement('identity_server_url', 'tr');
            hideElement('basic_configuration_text', 'p');
            hideElement('configuration_importation_button', 'p');
            hideElement('configuration_importation_back_button', 'p');
            hideSubmitButton();
        },
        // Advanced setup.
        2() {
            showElement('client_id', 'tr');
            showElement('secret_key', 'tr');
            hideElement('domain_code', 'tr');
            showElement('post_login_url', 'tr');
            showElement('user_autocreation', 'tr');
            showElement('user_creation_url', 'tr');
            $('#biocryptologylogin_api_config\\[user_autocreation\\]').trigger('change');
            showElement('claims', 'tr');
            showElement('delete_global_config', 'tr');
            hideElement('configuration_generation_button', 'tr');
            showElement('glosary', 'tr');
            hideElement('identity_server_url', 'tr');
            hideElement('basic_configuration_text', 'tr');
            hideElement('configuration_importation_button', 'tr');
            hideElement('configuration_importation_back_button', 'tr');
            showSubmitButton();
        }
    };

    function getConfigurationType(originalClientId) {
        const $configurationTypeSelect = $('#biocryptologylogin_api_config\\[configuration_type\\]');

        if (!$configurationTypeSelect.length) {
            return '2';
        }

        if (originalClientId) {
            hideElement('configuration_type', 'tr');

            return '2';
        }

        return $configurationTypeSelect.children('option:selected').val();
    }

    function makeConfigurationView(originalClientId) {
        configurations[getConfigurationType(originalClientId)]();
    }

    function setConfigurationView() {
        const $configurationTypeSelect = $('#biocryptologylogin_api_config\\[configuration_type\\]');
        const originalClientId         = $('#biocryptologylogin_api_config\\[client_id\\]').val();

        if ($configurationTypeSelect.length) {
            $configurationTypeSelect.on('change', function () {
                makeConfigurationView(originalClientId);
            }).trigger('change');
        }
    }

    function setConectionWithIdentityServer() {
        const $conectionButton = $('#biocryptologylogin_api_config\\[configuration_generation_button\\]');
        const $backButton = $('#biocryptologylogin_api_config\\[configuration_importation_back_button\\]');

        if ($conectionButton.length) {
            $conectionButton.on('click', function () {
                const identityServerUrl = document.getElementById('biocryptologylogin_api_config[identity_server_url]').value;
                const cmsInfo = document.getElementById('biocryptologylogin_api_config[cms_info]').value;

                if (identityServerUrl) {
                    hideElement('configuration_generation_button', 'p');
                    $('#biocryptologylogin_api_config\\[basic_configuration_text\\]').val('');
                    showElement('basic_configuration_text', 'p');
                    showElement('configuration_importation_button', 'p');
                    showElement('configuration_importation_back_button', 'p');
                    openWindowWithPostRequest(identityServerUrl, cmsInfo);
                }
            });
        }

        if ($backButton.length) {
            $backButton.on('click', function () {
                showElement('configuration_generation_button', 'p');
                hideElement('basic_configuration_text', 'p');
                hideElement('configuration_importation_button', 'p');
                hideElement('configuration_importation_back_button', 'p');
            }).parents('p').css('text-align', 'right');
        }
    }

    function isCorrectConfiguration(configurationParsed) {
        return configurationParsed.clientId;
    }

    function getDataFromImportedConfiguration(configurationParsed) {
        $('#biocryptologylogin_api_config\\[client_id\\]').val(configurationParsed.clientId);
        $('#biocryptologylogin_api_config\\[secret_key\\]').val(configurationParsed.clientSecret || '');
        $('#biocryptologylogin_api_config\\[domain_code\\]').val(configurationParsed.domain.code || '');
    }

    function setImportationProcess() {
        const $importationButton = $('#biocryptologylogin_api_config\\[configuration_importation_button\\]');

        if ($importationButton.length) {
            $importationButton.on('click', function () {
                const $configurationTxt = $('#biocryptologylogin_api_config\\[basic_configuration_text\\]');
                const configurationTxt = $configurationTxt.val().trim();
                let configurationParsed;

                if (configurationTxt) {
                    configurationParsed = JSON.parse(atob(configurationTxt));

                    if (isCorrectConfiguration(configurationParsed)) {
                        getDataFromImportedConfiguration(configurationParsed);
                        showElement('client_id', 'tr');
                        showElement('secret_key', 'tr');
                        showElement('post_login_url', 'tr');
                        showElement('user_autocreation', 'tr');
                        showElement('user_creation_url', 'tr');
                        $('#biocryptologylogin_api_config\\[user_autocreation\\]').trigger('change');
                        showElement('claims', 'tr');
                        showElement('delete_global_config', 'tr');
                        hideElement('configuration_generation_button', 'tr');
                        showElement('glosary', 'tr');
                        hideElement('identity_server_url', 'tr');
                        hideElement('basic_configuration_text', 'tr');
                        $configurationTxt.val('');
                        hideElement('configuration_importation_button', 'tr');
                        showSubmitButton();
                    }
                }
            });
        }
    }

    function getWindowParameters(width, height) {
        // Fixes dual-screen position                         Most browsers      Firefox
        const dualScreenLeft = window.screenLeft != null ? window.screenLeft : window.screenX;
        const dualScreenTop = window.screenTop != null ? window.screenTop : window.screenY;

        const calculatedWidth  = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
        const calculatedHeight = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

        width  = width || Math.floor(calculatedWidth * 0.66);
        height = height || Math.floor(calculatedHeight * 0.66);

        const left = ((calculatedWidth / 2) - (width / 2)) + dualScreenLeft;
        const top = ((calculatedHeight / 2) - (height / 2)) + dualScreenTop;

        return `iframe,top=${top},left=${left},width=${width},height=${height},resizable=yes,scrollbars=yes`;
    }

    function createHiddenInput(form, name, value) {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = name;
        input.value = value;
        form.appendChild(input);
    }

    function openWindowWithPostRequest(url, data) {
        const windowName = 'Bionet Window';
        const parameters = JSON.parse(atob(data));
        const form = document.createElement('form');

        if (url && parameters) {
            form.setAttribute('method', 'post');
            form.setAttribute('action', url);
            form.setAttribute('target', windowName);

            createHiddenInput(form, 'domain.domain', parameters.domain.domain);
            createHiddenInput(form, 'domain.description', parameters.domain.description);
            createHiddenInput(form, 'urls[0].url', parameters.urls[0].url);
            createHiddenInput(form, 'encodedLogo64', parameters.image);
            createHiddenInput(form, 'clientDescription', parameters.clientDescription);
            createHiddenInput(form, 'mandatoryClaims', parameters.mandatoryClaims);

            document.body.appendChild(form);
            document.body.appendChild(form);
            window.open('', windowName, getWindowParameters());
            form.target = windowName;
            form.submit();
            document.body.removeChild(form);
        }
    }

    $(document).ready(function () {
        // For callback url copy to clipboard.
        const clipboard               = new Clipboard('.biocryptologyloginclipboardtrigger');
        const $userAutocreationSelect = $('#biocryptologylogin_api_config\\[user_autocreation\\]');

        clipboard.on('success', function (e) {
            e.clearSelection();
        });

        // Select all text on click of callback url text.
        $('.biocryptologyloginclipboard').on('click', function () {
            const $this = $(this);
            const text   = $this.text();
            const $input = $('<input class="biocryptologyloginclipboard-text" type="text">');

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

        $userAutocreationSelect.on('change', function () {
            const isUserAutocreationEnabled = $(this).children('option:selected').val() === '1';
            const $userCreationUrl  = $('#biocryptologylogin_api_config\\[user_creation_url\\]');
            const redirectToSection = $userCreationUrl.parents('tr');

            redirectToSection.toggle(!isUserAutocreationEnabled);
            if (redirectToSection.is(':visible') && $userCreationUrl.val().trim() === '') {
                $userCreationUrl.val($('#user_creation_url_txt').val());
            }
        }).trigger('change');

        $('#submit').on('click', function () {
            const isUserAutocreationEnabled = $(this).children('option:selected').val() === '1';

            if (!isUserAutocreationEnabled) {
                const $userCreationUrl = $('#biocryptologylogin_api_config\\[user_creation_url\\]');
                if ($userCreationUrl.val().trim() === '') {
                    $userCreationUrl.val($('#user_creation_url_txt').val());
                }
            }
        });

        $('.tooltip').tooltipster({
            maxWidth: 300,
            theme   : 'tooltipster-light'
        });

        setConfigurationView();
        setConectionWithIdentityServer();
        setImportationProcess();
    });
})(window.jQuery);
