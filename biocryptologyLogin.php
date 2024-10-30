<?php

/**
 * @link              https://www.biocryptology.com
 * @since             1.0.0
 * @package           biocryptologylogin
 *
 * @wordpress-plugin
 * Plugin Name:       Biocryptology Login
 * Description:       Biocryptology Login for Wordpress
 * Version:           1.2.2
 * Author:            Biocryptology <plugins@biocryptology.com>
 * Author URI:        https://www.biocryptology.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       biocryptologylogin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-setting.php';
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use League\OAuth2\Client\Provider\Biocryptology;
use League\OAuth2\Client\Provider\BiocryptologyData;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

register_activation_hook(__FILE__, array('BiocryptologyLogin', 'activation'));
register_uninstall_hook(__FILE__, array('BiocryptologyLogin', 'uninstall'));

/**
 * BiocryptologyLogin
 *
 * @package    biocryptologylogin
 * @author     Biocryptology <plugins@biocryptology.com>
 */
class BiocryptologyLogin {
    const BIOCRYPTOLOGYLOGIN_API_CONFIG_STR    = 'biocryptologylogin_api_config';
    const BIOCRYPTOLOGYLOGIN_ERROR_STR         = 'biocryptologylogin_error';
    const BIOCRYPTOLOGYLOGIN_NOTICES_STR       = 'biocryptologylogin_notices';
    const BIOCRYPTOLOGY_REDIRECT_TO_STR        = 'biocryptology_redirect_to';
    const BIOCRYPTOLOGY_USERS_STR              = 'biocryptology_users';
    const CLASS_STR                            = 'class';
    const CLIENT_ID_STR                        = 'client_id';
    const CLIPBOARD_JS_HANDLE                  = 'clipboard.min';
    const CONTENT_STR                          = 'content';
    const DASHICON_BIOCRYPTOLOGY_STR           = 'dashicon-biocryptology';
    const DEFAULT_STR                          = 'default';
    const DELETE_GLOBAL_CONFIG_STR             = 'delete_global_config';
    const DESCRIPTION_STR                      = 'description';
    const DESC_TIP_STR                         = 'desc_tip';
    const DOMAIN_CODE_STR                      = 'domain_code';
    const EMAIL_STR                            = 'email';
    const FAMILY_NAME_STR                      = 'family_name';
    const GIVEN_NAME_STR                       = 'given_name';
    const JQUERY_JS_HANDLE                     = 'jquery';
    const JQUERY_VALIDATE_JS_HANDLE            = 'jquery.validate';
    const LABEL_SELECTOR_STR                   = 'label_selector';
    const LABEL_STR                            = 'label';
    const LARGE_STR                            = 'large';
    const NAME                                 = 'BiocryptologyLogin';
    const NEW_ADD_BIOCRYPTOLOGY_LOGIN_FORM_STR = 'new_add_biocryptology_login_form';
    const NOTICE_CLASS_STR                     = 'notice_class';
    const NOTICE_MSG_STR                       = 'notice_msg';
    const OPTIONS_STR                          = 'options';
    const REDIRECT_TO_STR                      = 'redirect_to';
    const SECRET_KEY_STR                       = 'secret_key';
    const SHOW_TYPE_STR                        = 'show_type';
    const STATE_STR                            = 'state';
    const STYLE_STR                            = 'style';
    const USER_AUTOCREATION_STR                = 'user_autocreation';
    const USER_CREATION_URL_STR                = 'user_creation_url';
    const USER_RESPONSE_STR                    = 'user_response';
    const VALUE_SELECTOR_STR                   = 'value_selector';
    const VERSION                              = '1.2.2';

    /** @var AccessToken $access_token */
    private $access_token;
    private $claims;
    private $client_id;
    private $client_secret;
    private $default_user_creation_url;
    private $domain_code;
    private $login_url;
    private $settings_api;
    private $user_autocreation;
    private $user_creation_url;

    /**
     * Initialize the class and set its properties.
     *
     * @param BiocryptologyLogin_Settings_API $settings_api
     */
    public function __construct(BiocryptologyLogin_Settings_API $settings_api) {
        $this->update_plugin(self::VERSION);

        // Load text domain.
        load_plugin_textdomain('biocryptologylogin', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $this->client_id                 = '';
        $this->client_secret             = '';
        $this->user_autocreation         = true;
        $this->user_creation_url         = '';
        $this->default_user_creation_url = trailingslashit(site_url()) . 'wp-login.php?action=register';
        $this->domain_code               = '';

        $biocryptologylogin_api_config = get_option(self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR);
        if (is_array($biocryptologylogin_api_config)) {
            if (isset($biocryptologylogin_api_config[self::CLIENT_ID_STR])) {
                $this->client_id = $biocryptologylogin_api_config[self::CLIENT_ID_STR];
            }

            if (isset($biocryptologylogin_api_config[self::SECRET_KEY_STR])) {
                $this->client_secret = $biocryptologylogin_api_config[self::SECRET_KEY_STR];
            }

            if (isset($biocryptologylogin_api_config[self::USER_AUTOCREATION_STR])) {
                $this->user_autocreation = (bool) $biocryptologylogin_api_config[self::USER_AUTOCREATION_STR];
                if (!$this->user_autocreation) {
                    $this->user_creation_url = $biocryptologylogin_api_config[self::USER_CREATION_URL_STR];
                }
            }

            $this->set_claims($biocryptologylogin_api_config);
            $this->domain_code = $biocryptologylogin_api_config[self::DOMAIN_CODE_STR];
        }

        $this->login_url    = $this->biocryptology_login_url();
        $this->settings_api = $settings_api;

        $this->generate_domain_check_file();
        $this->init();
    }

    /**
     * @param $plugin_version
     */
    private function update_plugin($plugin_version) {
        global $wpdb;

        $updated      = false;
        $site_version = get_site_option('biocryptologylogin_plugin_version', '1.0.0');

        if ($plugin_version !== $site_version && $plugin_version === '1.0.5') {
            $table_name = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;
            $updated    = $wpdb->query("ALTER TABLE {$table_name} MODIFY `response` MEDIUMTEXT");
        }

        if ($updated) {
            update_site_option('biocryptologylogin_plugin_version', $plugin_version);
        }
    }

    /**
     * @param array $biocryptologylogin_api_config
     */
    private function set_claims(array $biocryptologylogin_api_config) {
        $this->claims = [self::FAMILY_NAME_STR, self::GIVEN_NAME_STR, self::EMAIL_STR];

        if (isset($biocryptologylogin_api_config['claim-phone-number'])) {
            $this->claims[] = 'phone_number';
        }

        if (isset($biocryptologylogin_api_config['claim-locale'])) {
            $this->claims[] = 'locale';
        }

        if (isset($biocryptologylogin_api_config['claim-gender'])) {
            $this->claims[] = 'gender';
        }

        if (isset($biocryptologylogin_api_config['claim-date-of-birth'])) {
            $this->claims[] = 'birthdate';
        }

        if (isset($biocryptologylogin_api_config['claim-address'])) {
            $this->claims[] = 'address';
        }
    }

    /**
     * @return string
     */
    public function biocryptology_login_url() {
        $base_url = site_url('wp-login.php');
        $base_url .= (strpos($base_url, '?') === false) ? '?' : '&';

        return $base_url . 'loginbiocryptology=1';
    }

    private function generate_domain_check_file() {
        if ($this->domain_code) {
            $file      = "{$this->domain_code}.png";
            $file_path = plugin_dir_path(__FILE__) . "includes/assets/img/{$file}";

            if (!file_exists($file_path)) {
                file_put_contents($file_path, '', FILE_BINARY);
            }
        }
    }

    /**
     * Load the plugin on `init` hook
     *
     * @return void
     */
    public function init() {
        if (!session_id()) {
            session_start();
        }

        add_filter('init', array($this, 'new_biocryptology_add_query_var'));
        add_filter('login_init', array($this, 'new_biocryptology_login'));
        add_filter('login_message', array($this, 'add_biocryptologylogin_error_message'));

        // Show biocryptology connect url in login and register.
        add_action('login_form', array($this, self::NEW_ADD_BIOCRYPTOLOGY_LOGIN_FORM_STR));
        add_action('woocommerce_login_form', array($this, self::NEW_ADD_BIOCRYPTOLOGY_LOGIN_FORM_STR));
        add_action('register_form', array($this, self::NEW_ADD_BIOCRYPTOLOGY_LOGIN_FORM_STR));

        // Setting init and add  setting sub menu in setting menu.
        add_action('admin_init', array($this, 'setting_init'));
        add_action('admin_menu', array($this, 'biocryptologylogin_add_plugin_admin_menu'));
        add_action('admin_notices', array($this, 'biocryptologylogin_admin_notice_callback'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Plugin activation check
     */
    public static function activation() {
        global $wpdb;

        if (!current_user_can('activate_plugins')) {
            return;
        }

        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("activate-plugin_{$plugin}");

        $charset_collate = '';
        if (!empty($wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        }
        if (!empty($wpdb->collate)) {
            $charset_collate .= " COLLATE $wpdb->collate";
        }

        require_once ABSPATH . '/wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;
        $sql        = "CREATE TABLE $table_name (
                  `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `email` varchar(100) NOT NULL,
                  `identifier` varchar(100) NOT NULL,
                  response mediumtext DEFAULT NULL COMMENT 'biocryptology returned token and user response',
                  KEY `ID` (`ID`)
                ) $charset_collate; ";

        dbDelta($sql);
    }

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function uninstall() {
        global $wpdb;

        // Delete plugin global options.
        $prefix = 'biocryptologylogin_';
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'");

        $settings             = new BiocryptologyLogin_Settings_API();
        $delete_global_config = $settings->get_option(self::DELETE_GLOBAL_CONFIG_STR, 'biocryptologylogin_tools', 'no');

        if ($delete_global_config === 'yes') {
            // Delete tables created by this plugin.
            $table_name   = array();
            $table_name[] = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;

            $sql = 'DROP TABLE IF EXISTS ' . implode(', ', $table_name);
            $wpdb->query($sql);
        }
    }

    /**
     * Initialize setting
     */
    public function setting_init() {
        // Set the settings.
        $this->settings_api->set_sections($this->get_settings_sections());
        $this->settings_api->set_fields($this->get_settings_fields());

        // Initialize settings.
        $this->settings_api->admin_init();
    }

    /**
     * Global Setting Sections and titles
     *
     * @return mixed
     */
    public function get_settings_sections() {
        $settings_sections = array(array('id' => self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR));

        return apply_filters('biocryptologylogin_setting_sections', $settings_sections);
    }

    /**
     * Global Setting Fields
     *
     * @return array
     */
    public function get_settings_fields() {
        global $wpdb;

        $settings_builtin_fields =
            array(
                self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR => array(
                    'configuration_type'           => $this->get_configuration_type_field(),
                    'quick_setup_html'             => $this->get_quick_setup_html(),
                    'identity_server_url'          => $this->get_identity_server_url_field(),
                    'cms_info'                     => $this->get_cms_info_field(),
                    self::DOMAIN_CODE_STR          => $this->get_domain_code_field(),
                    self::CLIENT_ID_STR            => $this->get_client_id_field(),
                    'post_login_url'               => $this->get_post_login_url_field(),
                    self::USER_AUTOCREATION_STR    => $this->get_user_autocreation_field(),
                    self::USER_CREATION_URL_STR    => $this->get_user_creation_url_field(),
                    'user_claims'                  => $this->get_user_claims_field(),
                    self::DELETE_GLOBAL_CONFIG_STR => $this->get_delete_global_config_field($wpdb),
                    'glosary'                      => $this->get_glosary_field(),
                    'help-link'                    => $this->get_help_link_field()
                )
            );

        if (!$this->is_old_domain_without_secret_key()) {
            $settings_builtin_fields[self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR] = $this->array_insert_after(
                $settings_builtin_fields[self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR],
                self::CLIENT_ID_STR,
                array(
                    self::SECRET_KEY_STR => $this->get_secret_key_field()
                )
            );
        }

        // Final setting array that will be passed to different filters.
        $settings_fields = array();
        $sections        = $this->get_settings_sections();

        foreach ($sections as $section) {
            if (!isset($settings_builtin_fields[$section['id']])) {
                $settings_builtin_fields[$section['id']] = array();
            }

            $settings_builtin_fields_section_id = $settings_builtin_fields[$section['id']];
            $settings_fields[$section['id']]    = apply_filters(
                'biocryptologylogin_global_' . $section['id'] . '_fields',
                $settings_builtin_fields_section_id
            );
        }

        // Final filter if need.
        return apply_filters('biocryptologylogin_global_fields', $settings_fields);
    }

    /**
     * @return array
     */
    protected function get_configuration_type_field() {
        return array(
            'name'                   => 'configuration_type',
            self::LABEL_STR          => esc_html__('Configuration Type', 'biocryptologylogin'),
            'desc'                   => esc_html__(
                'Please, select a type of configuration.',
                'biocryptologylogin'
            ),
            'type'                   => 'select',
            self::OPTIONS_STR        => array(
                '0' => '-- ' . esc_html__('Configuration type', 'biocryptologylogin') . ' --',
                '1' => esc_html__('Quick setup', 'biocryptologylogin'),
                '2' => esc_html__('Advanced setup', 'biocryptologylogin')
            ),
            self::DEFAULT_STR        => 0,
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    /**
     * @return array
     */
    protected function get_quick_setup_html() {
        return array(
            'name'          => 'quick_setup_html',
            self::LABEL_STR => '',
            'desc'          => $this->get_quick_setup_view(),
            'type'          => 'html',
        );
    }

    /**
     * @return string
     */
    protected function get_quick_setup_view() {
        $text_1   = __('First you will need to install our app on your mobile phone:', 'biocryptologylogin');
        $text_2   = __('Hit "Begin" and scan the QR code that appears with our app to log in.', 'biocryptologylogin');
        $text_3   = __('A pre-completed form will appear with some information that we have extracted from your profile, but you can change whatever you like.', 'biocryptologylogin');
        $text_4   = __('When done, a code will be copied to your clipboard so that you can paste it in the field that will appear directly below ("Please, paste your code here").', 'biocryptologylogin');
        $text_5   = __('Hit "Finish" and the setup process is complete.', 'biocryptologylogin');
        $text_6_1 = __('Begin', 'biocryptologylogin');
        $text_6   = <<<HTML
<input type="button"
       class="button valid"
       id="biocryptologylogin_api_config[configuration_generation_button]"
       name="biocryptologylogin_api_config[configuration_generation_button]"
       value="{$text_6_1}"
       aria-invalid="false">
HTML;
        $text_7_1 = __('Please, paste your code here.', 'biocryptologylogin');
        $text_7   = <<<HTML
<textarea rows="5"
          cols="55"
          class="large-text"
          id="biocryptologylogin_api_config[basic_configuration_text]"
          name="biocryptologylogin_api_config[basic_configuration_text]"></textarea>
<span class="description"
      style="font-size: inherit;">{$text_7_1}</span>
HTML;
        $text_8_1 = __('Finish', 'biocryptologylogin');
        $text_8   = <<<HTML
<input type="button"
       class="button valid"
       id="biocryptologylogin_api_config[configuration_importation_button]"
       name="biocryptologylogin_api_config[configuration_importation_button]"
       value="{$text_8_1}"
       aria-invalid="false">
HTML;

        $text_9_1 = __('Back', 'biocryptologylogin');
        $text_9  = <<<HTML
<input type="button"
       class="button valid"
       id="biocryptologylogin_api_config[configuration_importation_back_button]"
       name="biocryptologylogin_api_config[configuration_importation_back_button]"
       value="{$text_9_1}"
       aria-invalid="false">
HTML;
        $text_10 = __('With the fast configuration, you can easily activate the biocryptology plug-in:', 'biocryptologylogin');

        $rows = [
            [
                [
                    self::STYLE_STR   => 'margin-bottom: 20px;',
                    self::CONTENT_STR => $text_1
                ],
                [
                    self::CONTENT_STR => $this->get_donwload_links_wrapper_html()
                ],
            ],
            [
                [
                    self::CONTENT_STR => $text_2
                ],
            ],
            [
                [
                    self::CONTENT_STR => $text_3
                ],
            ],
            [
                [
                    self::CONTENT_STR => $text_4
                ],
                [
                    self::CONTENT_STR => $text_5
                ],
                [
                    self::STYLE_STR   => 'text-align: center; margin: 30px 0;',
                    self::CONTENT_STR => __($text_6, 'biocryptologylogin')
                ],
                [
                    self::CLASS_STR   => self::DESCRIPTION_STR,
                    self::STYLE_STR   => 'margin: 30px 0 0;',
                    self::CONTENT_STR => __($text_7, 'biocryptologylogin')
                ],
                [
                    self::STYLE_STR   => 'text-align: center; margin: 30px 0;',
                    self::CONTENT_STR => __($text_8, 'biocryptologylogin')
                ],
                [
                    self::CLASS_STR   => self::DESCRIPTION_STR,
                    self::CONTENT_STR => __($text_9, 'biocryptologylogin')
                ]
            ]
        ];

        $text      = __($text_10, 'biocryptologylogin');
        $itemsHtml = $this->get_items_html($rows);

        return <<<HTML
<div style="margin-bottom: 15px;">{$text}</div>
<div class="quick-setup-wrapper">
    {$itemsHtml}
HTML;
    }

    protected function get_donwload_links_wrapper_html() {
        return <<<HTML
<div class="download-links-wrapper">
    <div>{$this->get_android_download_link()}</div>
    <div>{$this->get_ios_download_link()}</div>
    <div>&nbsp;</div>
    <div>{$this->get_android_app_qr()}</div>
    <div>{$this->get_ios_app_qr()}</div>
    <div>&nbsp;</div>
</div>
HTML;
    }

    protected function get_android_download_link() {
        $google_play_store = 'https://play.google.com/store/apps/details';
        $google_play_store .= '?id=com.biocryptology.mobile.biocryptology_android_app';

        return sprintf(
            '<a class="app-link" href="%1$s" target="_blank" rel="nofollow"><img src="%2$s" alt="%3$s" /></a>',
            esc_url($google_play_store),
            plugin_dir_url(__FILE__) . 'includes/assets/img/donwload-on-the-google-play-store-icon.png',
            esc_html__('Download on the Google Play Store', 'biocryptologylogin')
        );
    }

    protected function get_ios_download_link() {
        return sprintf(
            '<a class="app-link" href="%1$s" target="_blank" rel="nofollow"><img src="%2$s" alt="%3$s" /></a>',
            esc_url('https://apps.apple.com/es/app/biocryptology/id1334660999'),
            plugin_dir_url(__FILE__) . 'includes/assets/img/download-on-the-apple-store-icon.png',
            esc_html__('Download on the Apple Store', 'biocryptologylogin')
        );
    }

    protected function get_android_app_qr() {
        return sprintf(
            '<img src="%1$s" alt="%2$s" style="width: 130px;" />',
            plugin_dir_url(__FILE__) . 'includes/assets/img/qr-android-app.png',
            esc_html__('Download on the Google Play Store', 'biocryptologylogin')
        );
    }

    protected function get_ios_app_qr() {
        return sprintf(
            '<img src="%1$s" alt="%2$s" style="width: 130px;" />',
            plugin_dir_url(__FILE__) . 'includes/assets/img/qr-ios-app.png',
            esc_html__('Download on the Apple Store', 'biocryptologylogin')
        );
    }

    private function get_items_html(array $rows) {
        $html = '';

        foreach ($rows as $i => $row) {
            $index = $i + 1;
            $html  .= sprintf('<div><h1 style="padding: 0;"><span class="blue">%s</span></h1></div>', $index);
            $html  .= '<div>';

            foreach ($row as $item) {
                $style   = isset($item[self::STYLE_STR]) ? $item[self::STYLE_STR] : '';
                $class   = isset($item[self::CLASS_STR]) ? $item[self::CLASS_STR] : '';
                $content = isset($item[self::CONTENT_STR]) ? $item[self::CONTENT_STR] : '';
                $html    .= sprintf('<p class="%1$s" style="%2$s">%3$s</p>', $class, $style, $content);
            }

            $html .= '<div class="hr"><hr></div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @return array
     */
    protected function get_identity_server_url_field() {
        return array(
            'name'                   => 'identity_server_url',
            self::LABEL_STR          => '',
            'type'                   => 'text',
            'size'                   => self::LARGE_STR,
            self::DEFAULT_STR        => $this->get_identity_server_call(),
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    private function get_identity_server_call() {
        return BiocryptologyData::getAutomaticConfigurationApiUrl();
    }

    /**
     * @return string[]
     */
    protected function get_cms_info_field() {
        return array(
            'name'          => 'cms_info',
            self::LABEL_STR => '',
            'desc'          => $this->get_cms_info_field_description(),
            'type'          => 'html',
        );
    }

    /**
     * @return string
     */
    protected function get_cms_info_field_description() {
        return <<<HTML
<input id="biocryptologylogin_api_config[cms_info]" type="hidden" value="{$this->get_cms_info()}">
HTML;
    }

    private function get_cms_info() {
        $cmsInfo = [
            'clientDescription' => $this->get_site_name(),
            'image'             => $this->get_site_image_base64(),
            'urls'              => [['url' => $this->login_url]],
            'domain'            => [
                'domain'              => $this->get_site_url(),
                self::DESCRIPTION_STR => $this->get_site_description()
            ],
            'mandatoryClaims'   => [self::GIVEN_NAME_STR, self::EMAIL_STR, self::FAMILY_NAME_STR]
        ];

        return base64_encode(json_encode($cmsInfo));
    }

    private function get_site_name() {
        return get_bloginfo('name');
    }

    private function get_site_image_base64() {
        $customLogoId = get_theme_mod('custom_logo');
        $image        = wp_get_attachment_image_src($customLogoId, 'full');

        if (!$image) {
            return '';
        }

        return base64_encode(file_get_contents($image[0]));
    }

    private function get_site_url() {
        return get_bloginfo('url');
    }

    private function get_site_description() {
        return get_bloginfo(self::DESCRIPTION_STR);
    }

    /**
     * @return array
     */
    protected function get_domain_code_field() {
        return array(
            'name'           => self::DOMAIN_CODE_STR,
            'label'          => '',
            'type'           => 'text',
            'size'           => 'large',
            'default'        => '',
            'desc_tip'       => true,
            'label_selector' => false,
            'value_selector' => false,
            'show_type'      => array(),
        );
    }

    /**
     * @return array
     */
    protected function get_client_id_field() {
        return array(
            'name'                   => self::CLIENT_ID_STR,
            self::LABEL_STR          => esc_html__(
                                            'Client ID',
                                            'biocryptologylogin'
                                        ) . $this->mark_as_required_field(),
            'desc'                   => esc_html__(
                'Enter the client ID code copied from the plugin dashboard from your Biocryptology account.',
                'biocryptologylogin'
            ),
            'type'                   => 'text',
            'size'                   => self::LARGE_STR,
            self::DEFAULT_STR        => '',
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    /**
     * @return string
     */
    protected function mark_as_required_field() {
        return '&nbsp;<sup class="biocryptologylogin-required">*</sup>';
    }

    /**
     * @return array
     */
    protected function get_post_login_url_field() {
        return array(
            'name'                   => 'post_login_url',
            self::LABEL_STR          => esc_html__('Post Login URL', 'biocryptologylogin'),
            'desc'                   => esc_html__(
                'Copy the URL above and paste it on the plugin dashboard from your Biocryptology account.',
                'biocryptologylogin'
            ),
            'type'                   => 'redirect_uri_pattern',
            self::DEFAULT_STR        => $this->biocryptology_login_url(),
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    /**
     * @return array
     */
    protected function get_user_autocreation_field() {
        return array(
            'name'                   => self::USER_AUTOCREATION_STR,
            self::LABEL_STR          => esc_html__('Enable user registration', 'biocryptologylogin'),
            'desc'                   => esc_html__(
                'Select whether account creation is allowed or not via Biocryptology on your system.',
                'biocryptologylogin'
            ),
            'type'                   => 'select',
            self::OPTIONS_STR        => array(
                '1' => esc_html__('Yes', 'biocryptologylogin'),
                '0' => esc_html__('No', 'biocryptologylogin')
            ),
            self::DEFAULT_STR        => 1,
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    /**
     * @return array
     */
    protected function get_user_creation_url_field() {
        return array(
            'name'                   => self::USER_CREATION_URL_STR,
            self::LABEL_STR          => esc_html__(
                                            'Redirect to',
                                            'biocryptologylogin'
                                        ) . $this->mark_as_required_field(),
            'desc'                   => $this->get_user_creation_url_field_description(),
            'type'                   => 'text',
            'size'                   => self::LARGE_STR,
            self::DEFAULT_STR        => $this->default_user_creation_url,
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
            'sanitize_callback'      => [$this, 'get_user_creation_url']
        );
    }

    /**
     * @return string
     */
    protected function get_user_creation_url_field_description() {
        $msg = 'Enter the URL to which non registered users will be redirected. '
               . 'Note that this URL must belong to the same site\'s domain.';

        return esc_html__(
            $msg,
            'biocryptologylogin'
        );
    }

    /**
     * @return array
     */
    protected function get_user_claims_field() {
        return array(
            'name'          => 'user_claims',
            self::LABEL_STR => $this->get_claims_label_html(),
            'desc'          => $this->get_claims_configuration_html(),
            'type'          => 'html',
        );
    }

    /**
     * @return string
     */
    private function get_claims_label_html() {
        $text1 = esc_html__('Important info', 'biocryptologylogin');
        $text2 = esc_html__(
            'To complete the configuration, make sure you select the same data on the plugin dashboard from your Biocryptology account.',
            'biocryptologylogin'
        );

        $html = <<<HTML
<span class="icono-info tooltip"
      style="margin-left: 3px;"
      data-tooltip-content="#tooltip_content"></span>
<div class="tooltip_templates">
    <span id="tooltip_content">
        <span class="icono-info-tooltip" style="margin-right: 3px; vertical-align: middle;"></span>
        <span class="tooltip-title">{$text1}</span>
        <p>{$text2}</p>
    </span>
</div>
HTML;

        return esc_html__('User data configuration', 'biocryptologylogin') . $html;
    }

    private function get_claims_configuration_html() {
        $messages = array(
            esc_html__(
                'The following data will always be sent to your system as it is required by your CMS on user creation:',
                'biocryptologylogin'
            ),
            esc_html__(
                'Select the data you would like to receive from users. Note that you also need to configure them on the plugin dashboard from your Biocryptology account:',
                'biocryptologylogin'
            ),
            esc_html__('Phone Number (verified)', 'biocryptologylogin'),
            esc_html__('Language', 'biocryptologylogin'),
            esc_html__('Sex', 'biocryptologylogin'),
            esc_html__('Date of Birth', 'biocryptologylogin'),
            esc_html__('Address', 'biocryptologylogin'),
            esc_html__('Given Names', 'biocryptologylogin'),
            esc_html__('Surname', 'biocryptologylogin'),
            esc_html__('Email Address (verified)', 'biocryptologylogin'),
        );

        $claim_phone_number_checked = $this->get_check_state('claim-phone-number');
        $claim_locale_checked       = $this->get_check_state('claim-locale');
        $claim_gender_checked       = $this->get_check_state('claim-gender');
        $claim_date_of_birth        = $this->get_check_state('claim-date-of-birth');
        $claim_address_checked      = $this->get_check_state('claim-address');

        return <<<HTML
<div id="biocryptologylogin_api_config[claims]" name="biocryptologylogin_api_config[claims]" class="claims">
    <p>{$messages[0]}</p>
    <ul>
        <li class="readonly">{$messages[7]}</li>
        <li class="readonly">{$messages[8]}</li>
        <li class="readonly">{$messages[9]}</li>
    </ul>
    <p style="margin-bottom: 15px;">{$messages[1]}</p>
    <p>
        <input type="checkbox"
               id="claim-phone-number"
               name="biocryptologylogin_api_config[claim-phone-number]"
               value="1"
               {$claim_phone_number_checked}><label for="claim-phone-number">{$messages[2]}</label>
    </p>
    <p>
        <input type="checkbox"
               id="claim-locale"
               name="biocryptologylogin_api_config[claim-locale]"
               value="1"
               {$claim_locale_checked}><label for="claim-locale">{$messages[3]}</label>
    </p>
    <p>
        <input type="checkbox"
               id="claim-gender"
               name="biocryptologylogin_api_config[claim-gender]"
               value="1"
               {$claim_gender_checked}><label for="claim-gender">{$messages[4]}</label>
    </p>
    <p>
        <input type="checkbox"
               id="claim-date-of-birth"
               name="biocryptologylogin_api_config[claim-date-of-birth]"
               value="1"
               {$claim_date_of_birth}><label for="claim-date-of-birth">{$messages[5]}</label>
    </p>
    <p>
        <input type="checkbox"
               id="claim-address"
               name="biocryptologylogin_api_config[claim-address]"
               value="1"
               {$claim_address_checked}><label for="claim-address">{$messages[6]}</label>
    </p>
</div>
HTML;
    }

    private function get_check_state($checkbox_name) {
        $biocryptologylogin_api_config = get_option(self::BIOCRYPTOLOGYLOGIN_API_CONFIG_STR, []);
        if (isset($biocryptologylogin_api_config[$checkbox_name])
            && $biocryptologylogin_api_config[$checkbox_name] === '1') {
            return ' checked ';
        }

        return '';
    }

    /**
     * @param wpdb $wpdb
     *
     * @return array
     */
    protected function get_delete_global_config_field(wpdb $wpdb) {
        return array(
            'name'             => self::DELETE_GLOBAL_CONFIG_STR,
            self::LABEL_STR    => esc_html__('On Uninstall delete plugin data', 'biocryptologylogin'),
            'desc'             => $this->get_delete_global_config_field_description($wpdb),
            'type'             => 'radio',
            self::OPTIONS_STR  => array(
                'yes' => esc_html__('Yes', 'biocryptologylogin'),
                'no'  => esc_html__('No', 'biocryptologylogin'),
            ),
            self::DEFAULT_STR  => 'no',
            self::DESC_TIP_STR => true,
        );
    }

    /**
     * @param wpdb $wpdb
     *
     * @return string
     */
    protected function get_delete_global_config_field_description(wpdb $wpdb) {
        $msg = __(
            'Delete Global Config data and custom table (%sbiocryptology_users) created by this plugin on uninstall. <br/><strong>Please note that this can not be undone and you keep proper backup of full database before using feature.</strong>',
            'biocryptologylogin'
        );

        return sprintf($msg, $wpdb->prefix);
    }

    /**
     * @return string[]
     */
    protected function get_glosary_field() {
        return array(
            'name'          => 'glosary',
            self::LABEL_STR => $this->get_glosary_field_html(),
            'type'          => 'html',
        );
    }

    /**
     * @return string
     */
    protected function get_glosary_field_html() {
        return '<small name="biocryptologylogin_api_config[glosary]" class="biocryptologylogin-required">(*) ' . esc_html__(
                'Required fields.',
                'biocryptologylogin'
            ) . '</small>';
    }

    /**
     * @return array
     */
    protected function get_help_link_field() {
        return array(
            'name'          => 'help-link',
            self::LABEL_STR => '',
            'type'          => 'html',
            'desc'          => $this->get_help_link()
        );
    }

    /**
     * @return string
     */
    protected function get_help_link() {
        $msg = esc_html__('Help', 'biocryptologylogin');

        return <<<HTML
<div style="text-align: right;"><a style="text-decoration-line: none; box-shadow: none; vertical-align: text-top;"
                                   target="_blank"
                                   href="https://biocryptology.zendesk.com"
                                   alt="Help">{$msg} <span class="dashicons dashicons-editor-help"></span></a></div>
HTML;
    }

    private function is_old_domain_without_secret_key() {
        return $this->client_id && !$this->client_secret;
    }

    private function array_insert_after(array $array, $key, array $new) {
        $keys  = array_keys($array);
        $index = array_search($key, $keys, true);
        $pos   = false === $index ? count($array) : $index + 1;

        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    }

    /**
     * @return array
     */
    protected function get_secret_key_field() {
        return array(
            'name'                   => self::SECRET_KEY_STR,
            self::LABEL_STR          => esc_html__(
                                            'Secret Key',
                                            'biocryptologylogin'
                                        ) . $this->mark_as_required_field(),
            'desc'                   => esc_html__(
                'Enter the secret key code copied from the plugin dashboard from your Biocryptology account.',
                'biocryptologylogin'
            ),
            'type'                   => 'text',
            'size'                   => self::LARGE_STR,
            self::DEFAULT_STR        => '',
            self::DESC_TIP_STR       => true,
            self::LABEL_SELECTOR_STR => false,
            self::VALUE_SELECTOR_STR => false,
            self::SHOW_TYPE_STR      => array(),
        );
    }

    /**
     *
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     */
    public function biocryptologylogin_add_plugin_admin_menu() {
        add_menu_page(
            esc_html__('Biocryptology API Configuration', 'biocryptologylogin'),
            esc_html__('Biocryptology Login', 'biocryptologylogin'),
            'manage_options',
            'biocryptologysettings',
            array($this, 'display_plugin_admin_page'),
            'dashicons-biocryptology'
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        include_once 'templates/admin-settings-display.php';
    }

    /**
     * add loginbiocryptology query parameter to login url
     */
    public function new_biocryptology_add_query_var() {
        global $wp;

        $wp->add_query_var('loginbiocryptology');
    }

    /**
     * show Biocryptology connect login link
     */
    public function new_add_biocryptology_login_form() {
        if ($this->client_id !== '') {
            echo $this->biocryptology_login_link();
            $this->login_scripts();
        }
    }

    /**
     * @return string
     */
    public function biocryptology_login_link() {
        $href = esc_url($this->biocryptology_login_url() . $this->get_redirect_param());
        $text = esc_html__('Biocryptology Login', 'biocryptologylogin');

        return <<<HTML
<button type="button" class="biocryptologylogin-btn dashicons-before dashicons-biocryptology"
        style="margin-bottom: 3px; text-align: left;"
        onclick="document.location.href = '{$href}'"><span>{$text}</span></button>
HTML;
    }

    /**
     *
     * @return string
     */
    protected function get_redirect_param() {
        return isset($_GET[self::REDIRECT_TO_STR]) ? '&redirect=' . urlencode($_GET[self::REDIRECT_TO_STR]) : '';
    }

    public function login_scripts() {
        wp_enqueue_script(
            'login-button-position',
            plugin_dir_url(__FILE__) . 'includes/assets/js/login-button-position.js',
            array(self::JQUERY_JS_HANDLE),
            self::VERSION,
            true
        );

        wp_enqueue_style(
            'login-button-styles',
            plugin_dir_url(__FILE__) . 'includes/assets/css/login-button-styles.css',
            array(),
            self::VERSION
        );

        wp_enqueue_style(
            self::DASHICON_BIOCRYPTOLOGY_STR,
            plugin_dir_url(
                __FILE__
            ) . 'includes/assets/css/dashicon-biocryptology/css/biofont.css',
            array(),
            self::VERSION
        );
    }

    /**
     * if Biocryptology connect url click then check if loginbiocryptology param exist and redirect yes to action
     */
    public function new_biocryptology_login() {
        if (isset($_REQUEST['loginbiocryptology']) && (int) $_REQUEST['loginbiocryptology'] === 1) {
            $this->biocryptology_login_action();
        }
    }

    /**
     * biocryptology auth functionality
     */
    public function biocryptology_login_action() {
        if (!session_id()) {
            session_start();
        }

        $providerOptions = $this->get_provider_options();
        $provider        = new Biocryptology($providerOptions);

        if (!isset($_SESSION[self::BIOCRYPTOLOGY_REDIRECT_TO_STR])) {
            $redirect_to                                   = isset($_GET[self::REDIRECT_TO_STR])
                ? $_GET[self::REDIRECT_TO_STR]
                : null;
            $_SESSION[self::BIOCRYPTOLOGY_REDIRECT_TO_STR] = $redirect_to;
        }

        // Use code param.
        if (!isset($_GET['code'])) {
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $Authorizationoptions = $this->get_array_with_claims_parameter() + [
                    self::STATE_STR => $this->create_onetime_nonce(
                        'state-check'
                    )
                ];
            $authorizationUrl     = $provider->getAuthorizationUrl($Authorizationoptions);

            // Get the state generated for you and store it to the session.
            $_SESSION[self::STATE_STR] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;
        }

        // Check if nonce is valid.
        if (!$this->is_valid_state_parameter()) {
            unset($_REQUEST[self::STATE_STR]);
            $this->redirect_to_login_page_with_custom_error_message(
                esc_html__('Invalid state parameter.', 'biocryptologylogin')
            );
        }

        // Try to get an access token (using the authorization code grant).
        $response        = $this->get_response_from_provider($provider);
        $given_user_info = $response->toArray();
        $token_info      = $this->access_token->getValues();

        $user_mail = $this->get_sanitized_email($given_user_info);
        $user_info = get_user_by(self::EMAIL_STR, $user_mail);

        // If user exist by email then check exist in biocryptologylogin_users table.
        // If exist then update identifier else insert.
        if ($user_info && !$this->is_recycled_user($user_info, $given_user_info)) {
            $user_id = (int) $user_info->ID;
            $this->update_user($user_id, $user_mail, $token_info, $given_user_info);
        } else {
            $user_id = $this->create_user($user_mail, $token_info, $given_user_info);
        }

        if (isset($_SESSION[self::BIOCRYPTOLOGY_REDIRECT_TO_STR])) {
            $redirect_to = $_SESSION[self::BIOCRYPTOLOGY_REDIRECT_TO_STR];
            unset($_SESSION[self::BIOCRYPTOLOGY_REDIRECT_TO_STR]);
            wp_safe_redirect($redirect_to);
            exit;
        }

        // Login with redirect.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        wp_redirect(home_url());
        exit;
    }

    private function get_provider_options() {
        return [
            'clientId'     => $this->client_id,
            'clientSecret' => $this->client_secret,
            'redirectUri'  => $this->login_url
        ];
    }

    private function get_array_with_claims_parameter() {
        $this->claims = array_unique($this->claims);

        if (empty($this->claims)) {
            return [];
        }

        return [
            'claims' => $this->get_claims_parameter_as_json()
        ];
    }

    private function get_claims_parameter_as_json() {
        return json_encode(
            [
                'userinfo' => array_map(
                    static function () {
                        return null;
                    },
                    array_flip($this->claims)
                )
            ]
        );
    }

    /**
     * @param int $action
     *
     * @return string
     */
    private function create_onetime_nonce($action = - 1) {
        $time  = time();
        $nonce = wp_create_nonce($time . $action);

        return $nonce . '-' . $time;
    }

    /**
     * @return bool
     */
    private function is_valid_state_parameter() {
        return isset($_REQUEST[self::STATE_STR]) && $this->is_onetime_nonce($_REQUEST[self::STATE_STR], 'state-check');
    }

    /**
     * @param $_nonce
     * @param int $action
     *
     * @return bool
     */
    private function is_onetime_nonce($_nonce, $action = - 1) {
        // Extract timestamp and nonce part of $_nonce.
        list($nonce, $generated) = explode('-', $_nonce);

        // We want these nonces to have a short lifespan.
        $nonce_life = 60 * 60;
        $expires    = (int) $generated + $nonce_life;

        // Current time.
        $time = time();

        // Verify the nonce part and check that it has not expired.
        if ($time > $expires || !wp_verify_nonce($nonce, $generated . $action)) {
            return false;
        }

        // Get used nonces.
        $used_nonces = get_option('biocryptology_used_nonces', array());

        // Nonce already used.
        if (isset($used_nonces[$nonce])) {
            return false;
        }

        foreach ($used_nonces as $nonce => $timestamp) {
            if ($timestamp > $time) {
                break;
            }

            // This nonce has expired, so we don't need to keep it any longer.
            unset($used_nonces[$nonce]);
        }

        // Add nonce to used nonces and sort.
        $used_nonces[$nonce] = $expires;
        asort($used_nonces);
        update_option('biocryptology_used_nonces', $used_nonces);

        return true;
    }

    /**
     * @param $message
     */
    protected function redirect_to_login_page_with_custom_error_message($message) {
        $_SESSION[self::BIOCRYPTOLOGYLOGIN_ERROR_STR] = __($message);
        wp_redirect(wp_login_url());
        exit;
    }

    /**
     * @param Biocryptology $provider
     *
     * @return ResourceOwnerInterface
     */
    protected function get_response_from_provider(Biocryptology $provider) {
        try {
            $this->access_token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);

            // Retrive the userinfo from url using access token.
            if (!$this->is_valid_access_token()) {
                $this->redirect_to_login_page_with_custom_error_message('Invalid access_token parameter.');
            }

        } catch (IdentityProviderException $e) {
            $this->redirect_to_login_page_with_custom_error_message($e->getMessage());
        }

        // We got an access token, let's now get the owner details.
        return $provider->getResourceOwner($this->access_token);
    }

    /**
     * @return bool
     */
    private function is_valid_access_token() {
        return $this->access_token->getToken() !== '';
    }

    /**
     * @param array $given_user_info
     *
     * @return string
     */
    protected function get_sanitized_email(array $given_user_info) {
        $original_user_mail = trim($given_user_info[self::EMAIL_STR]);
        $user_mail          = sanitize_email($original_user_mail);

        if ($original_user_mail !== $user_mail) {
            $this->redirect_to_login_page_with_custom_error_message(
                esc_html__('Invalid email address.', 'biocryptologylogin')
            );
        }

        return $user_mail;
    }

    /**
     * @param bool|WP_User $user_info
     *
     * @param array $given_user_info
     *
     * @return bool
     */
    private function is_recycled_user($user_info, array $given_user_info) {
        global $wpdb;

        $is_recicled = false;

        if (is_object($user_info) && $given_user_info) {
            $user_id                   = (int) $user_info->ID;
            $user_mail                 = $user_info->user_email;
            $biocryptology_users_table = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;
            $query                     = <<<SQL
SELECT response FROM $biocryptology_users_table WHERE email = %s AND user_id = %d
SQL;
            $response                  = $wpdb->get_var($wpdb->prepare($query, $user_mail, $user_id));
            $response_unserialized     = unserialize($response);

            if (isset($response_unserialized[self::USER_RESPONSE_STR]['sub'])) {
                $is_recicled = $response_unserialized[self::USER_RESPONSE_STR]['sub'] !== $given_user_info['sub'];
            }
        }

        return $is_recicled;
    }

    /**
     * @param $user_id
     * @param $user_mail
     * @param array $token_info
     * @param array $given_user_info
     */
    protected function update_user($user_id, $user_mail, array $token_info, array $given_user_info) {
        if (is_user_logged_in() && ($user_mail !== wp_get_current_user()->user_email)) {
            $_SESSION[self::BIOCRYPTOLOGYLOGIN_NOTICES_STR] = array(
                self::NOTICE_CLASS_STR => 'warning',
                self::NOTICE_MSG_STR   => esc_html__(
                    'This Biocryptology account is already linked with other account. If you want to connect, then disconnect that account first and then try again!',
                    'biocryptologylogin'
                )
            );
        } else {
            global $wpdb;

            $biocryptology_users_table = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;
            $query                     = "SELECT ID FROM $biocryptology_users_table WHERE email = %s AND user_id = %d";
            $identifier_holder_exist   = $wpdb->get_var($wpdb->prepare($query, $user_mail, $user_id));

            if ($identifier_holder_exist === null) {
                $this->insert_biocryptology_table($user_id, $token_info, $given_user_info);
            } else {
                $this->update_biocryptology_table($user_id, $token_info, $given_user_info);
            }
        }
    }

    /**
     * Insert Biocryptology users into plugin's user ref table
     *
     * @param $user_id
     * @param $info
     * @param $given_user_info
     */
    public function insert_biocryptology_table($user_id, $info, $given_user_info) {
        global $wpdb;

        $user_mail = sanitize_email($given_user_info[self::EMAIL_STR]);
        $this->clean_biocryptology_table($user_mail);

        $biocryptology_users_table = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;

        $response_arr = array(
            'token_response'        => $info,
            self::USER_RESPONSE_STR => $given_user_info
        );

        $data_to_insert = array(
            'user_id'       => (int) $user_id,
            self::EMAIL_STR => $user_mail,
            'identifier'    => $this->access_token->getToken(),
            'response'      => maybe_serialize($response_arr)
        );

        $data_format = array(
            '%d', // user_id
            '%s', // email
            '%s', // identifier
            '%s'  // response
        );

        $wpdb->insert(
            $biocryptology_users_table,
            $data_to_insert,
            $data_format
        );

        $_SESSION[self::BIOCRYPTOLOGYLOGIN_NOTICES_STR] = array(
            self::NOTICE_CLASS_STR => 'success',
            self::NOTICE_MSG_STR   => esc_html__(
                'Your Biocryptology profile is successfully linked with your account. Now you can sign in with Biocryptology easily.',
                'biocryptologylogin'
            )
        );
    }

    /**
     * @param $user_mail
     */
    private function clean_biocryptology_table($user_mail) {
        global $wpdb;

        $biocryptology_users_table = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;
        $wpdb->delete($biocryptology_users_table, array(self::EMAIL_STR => $user_mail), array('%s'));
    }

    /**
     * @param $user_id
     * @param array $token_info
     * @param array $given_user_info
     */
    protected function update_biocryptology_table($user_id, array $token_info, array $given_user_info) {
        global $wpdb;

        $response_arr = array(
            'token_response'        => $token_info,
            self::USER_RESPONSE_STR => $given_user_info
        );

        $data_to_update = array(
            'identifier' => $this->access_token->getToken(), // String.
            'response'   => maybe_serialize($response_arr)
        );

        $data_format = array(
            '%s', // Identifier.
            '%s'  // Response.
        );

        $biocryptology_users_table = $wpdb->prefix . self::BIOCRYPTOLOGY_USERS_STR;

        $where        = array('user_id' => $user_id);
        $where_format = array('%d');

        $wpdb->update(
            $biocryptology_users_table,
            $data_to_update,
            $where,
            $data_format,
            $where_format
        );
    }

    /**
     * @param $user_mail
     * @param array $token_info
     * @param array $given_user_info
     *
     * @return false|int|WP_Error
     */
    protected function create_user($user_mail, array $token_info, array $given_user_info) {
        if (!$this->is_user_autocreation_enabled()) {
            return false;
        }

        $user_id = email_exists($user_mail);
        if ($user_id) {
            wp_delete_user($user_id);
        }

        $random_password = wp_generate_password(12, false);
        $user_id         = wp_insert_user(
            array(
                'user_login'   => $user_mail,
                'user_email'   => $user_mail,
                'user_pass'    => $random_password,
                'first_name'   => $given_user_info[self::GIVEN_NAME_STR],
                'last_name'    => $given_user_info[self::FAMILY_NAME_STR],
                'display_name' => $given_user_info[self::GIVEN_NAME_STR] . ' ' . $given_user_info[self::FAMILY_NAME_STR]
            )
        );

        if (is_wp_error($user_id)) {
            $this->redirect_to_login_page_with_custom_error_message($user_id->get_error_message());
        }

        $this->insert_biocryptology_table($user_id, $token_info, $given_user_info);

        if (class_exists('WooCommerce')) {
            update_user_meta($user_id, 'billing_first_name', $given_user_info[self::GIVEN_NAME_STR]);
            update_user_meta($user_id, 'billing_last_name', $given_user_info[self::FAMILY_NAME_STR]);
            update_user_meta($user_id, 'billing_company', $this->get_billing_company($given_user_info));
            update_user_meta($user_id, 'billing_address_1', $this->get_billing_addres_1($given_user_info));
            update_user_meta($user_id, 'billing_address_2', $this->get_billing_addres_2($given_user_info));
            update_user_meta($user_id, 'billing_city', $this->get_billing_city($given_user_info));
            update_user_meta($user_id, 'billing_postcode', $this->get_billing_postcode($given_user_info));
            update_user_meta($user_id, 'billing_country', $this->get_billing_country($given_user_info));
            update_user_meta($user_id, 'billing_state', $this->get_billing_state($given_user_info));
            update_user_meta($user_id, 'billing_email', $user_mail);
            update_user_meta($user_id, 'billing_phone', $this->get_billing_phone($given_user_info));
            update_user_meta($user_id, 'shipping_first_name', $given_user_info[self::GIVEN_NAME_STR]);
            update_user_meta($user_id, 'shipping_last_name', $given_user_info[self::FAMILY_NAME_STR]);
            update_user_meta($user_id, 'shipping_address_1', $this->get_shipping_address_1($given_user_info));
            update_user_meta($user_id, 'shipping_address_2', $this->get_shipping_address_2($given_user_info));
            update_user_meta($user_id, 'shipping_city', $this->get_shipping_city($given_user_info));
            update_user_meta($user_id, 'shipping_postcode', $this->get_shipping_postcode($given_user_info));
            update_user_meta($user_id, 'shipping_country', $this->get_shipping_country($given_user_info));
            update_user_meta($user_id, 'shipping_state', $this->get_shipping_state($given_user_info));
        }

        return $user_id;
    }

    protected function is_user_autocreation_enabled() {
        if (!$this->user_autocreation) {
            wp_redirect($this->user_creation_url);
            exit;
        }

        return true;
    }

    protected function get_billing_company(array $given_user_info) {
        return '';
    }

    protected function get_billing_addres_1(array $given_user_info) {
        if (!isset($given_user_info['address']) || empty($given_user_info['address'])) {
            return '';
        }

        if (!isset($given_user_info['address']['street_address'])) {
            return '';
        }

        return $given_user_info['address']['street_address'];
    }

    protected function get_billing_addres_2(array $given_user_info) {
        return '';
    }

    protected function get_billing_city(array $given_user_info) {
        if (!isset($given_user_info['address']) || empty($given_user_info['address'])) {
            return '';
        }

        if (!isset($given_user_info['address']['locality'])) {
            return '';
        }

        return $given_user_info['address']['locality'];
    }

    protected function get_billing_postcode(array $given_user_info) {
        if (!isset($given_user_info['address']) || empty($given_user_info['address'])) {
            return '';
        }

        if (!isset($given_user_info['address']['postal_code'])) {
            return '';
        }

        return $given_user_info['address']['postal_code'];
    }

    protected function get_billing_country(array $given_user_info) {
        if (!isset($given_user_info['address']) || empty($given_user_info['address'])) {
            return '';
        }

        if (!isset($given_user_info['address']['country'])) {
            return '';
        }

        return $given_user_info['address']['country'];
    }

    protected function get_billing_state(array $given_user_info) {
        if (!isset($given_user_info['address']) || empty($given_user_info['address'])) {
            return '';
        }

        if (!isset($given_user_info['address']['region'])) {
            return '';
        }

        return $given_user_info['address']['region'];
    }

    protected function get_billing_phone(array $given_user_info) {
        if (!isset($given_user_info['phone_number'])) {
            return '';
        }

        return $given_user_info['phone_number'];
    }

    protected function get_shipping_address_1(array $given_user_info) {
        return $this->get_billing_addres_1($given_user_info);
    }

    protected function get_shipping_address_2(array $given_user_info) {
        return $this->get_billing_addres_2($given_user_info);
    }

    protected function get_shipping_city(array $given_user_info) {
        return $this->get_billing_city($given_user_info);
    }

    protected function get_shipping_postcode(array $given_user_info) {
        return $this->get_billing_postcode($given_user_info);
    }

    protected function get_shipping_country(array $given_user_info) {
        return $this->get_billing_country($given_user_info);
    }

    protected function get_shipping_state(array $given_user_info) {
        return $this->get_billing_state($given_user_info);
    }

    /**
     * Add message above login form.
     */
    public function add_biocryptologylogin_error_message() {
        if (!isset($_SESSION[self::BIOCRYPTOLOGYLOGIN_ERROR_STR])
            || empty($_SESSION[self::BIOCRYPTOLOGYLOGIN_ERROR_STR])
        ) {
            return '';
        }

        $message = __($_SESSION[self::BIOCRYPTOLOGYLOGIN_ERROR_STR], 'biocryptologylogin');
        unset($_SESSION[self::BIOCRYPTOLOGYLOGIN_ERROR_STR]);

        return <<<HTML
<div id="login_error">
    <strong>ERROR</strong>: $message<br>
</div>
HTML;
    }

    /**
     * return current page url
     * @return string
     */
    public function get_current_page_url() {
        $pageURL = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $pageURL .= 's';
        }
        $pageURL .= '://';
        if ($_SERVER['SERVER_PORT'] !== '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        return $pageURL;
    }

    /**
     * all admin notices
     */
    public function biocryptologylogin_admin_notice_callback() {
        if (isset($_SESSION[self::BIOCRYPTOLOGYLOGIN_NOTICES_STR])) {
            $notices = $_SESSION[self::BIOCRYPTOLOGYLOGIN_NOTICES_STR];
            unset($_SESSION[self::BIOCRYPTOLOGYLOGIN_NOTICES_STR]);

            ?>
            <div class="notice notice-<?php echo $notices[self::NOTICE_CLASS_STR]; ?> is-dismissible">
                <p><?php echo $notices[self::NOTICE_MSG_STR]; ?></p>
            </div>
            <?php
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @param $hook
     *
     * @since    1.0.0
     *
     */
    public function enqueue_styles($hook) {
        if ($hook === 'toplevel_page_biocryptologysettings') {
            $tooltipster_path = 'includes/assets/js/tooltipster';

            wp_register_style(
                'biocryptologylogin-style',
                plugin_dir_url(__FILE__) . 'includes/assets/css/biocryptologylogin-style.css',
                array(),
                self::VERSION
            );
            wp_register_style(
                'tooltipster-style',
                plugin_dir_url(__FILE__) . "{$tooltipster_path}/css/tooltipster.bundle.min.css",
                array(),
                self::VERSION
            );
            wp_register_style(
                'tooltipster-light-theme',
                plugin_dir_url(
                    __FILE__
                ) . "{$tooltipster_path}/css/plugins/tooltipster/sideTip/themes/tooltipster-sideTip-light.min.css",
                array(),
                self::VERSION
            );

            wp_enqueue_style('biocryptologylogin-style');
            wp_enqueue_style('tooltipster-style');
            wp_enqueue_style('tooltipster-light-theme');
        }

        wp_register_style(
            self::DASHICON_BIOCRYPTOLOGY_STR,
            plugin_dir_url(
                __FILE__
            ) . 'includes/assets/css/dashicon-biocryptology/css/biofont.css',
            array(),
            self::VERSION
        );

        wp_enqueue_style(self::DASHICON_BIOCRYPTOLOGY_STR);
    }

    /**
     * Register the javascripts for the admin area.
     *
     * @param $hook
     *
     * @since    1.0.0
     *
     */
    public function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_biocryptologysettings') {
            // Register clipboard.
            wp_register_script(
                self::CLIPBOARD_JS_HANDLE,
                plugin_dir_url(__FILE__) . 'includes/assets/js/clipboard.min.js',
                array(self::JQUERY_JS_HANDLE),
                self::VERSION,
                true
            );
            wp_register_script(
                'tooltipster',
                plugin_dir_url(__FILE__) . 'includes/assets/js/tooltipster/js/tooltipster.bundle.min.js',
                array(self::JQUERY_JS_HANDLE),
                self::VERSION,
                true
            );
            wp_register_script(
                'biocryptologylogin-script',
                plugin_dir_url(__FILE__) . 'includes/assets/js/biocryptologylogin-script.js',
                array(self::JQUERY_JS_HANDLE, self::CLIPBOARD_JS_HANDLE),
                self::VERSION
            );
            wp_register_script(
                self::JQUERY_VALIDATE_JS_HANDLE,
                plugin_dir_url(__FILE__) . 'includes/assets/js/jquery.validate.min.js',
                array(self::JQUERY_JS_HANDLE),
                self::VERSION
            );
            wp_register_script(
                'form-validation',
                plugin_dir_url(__FILE__) . 'includes/assets/js/form-validation.js',
                array(self::JQUERY_JS_HANDLE, self::JQUERY_VALIDATE_JS_HANDLE),
                self::VERSION
            );

            wp_enqueue_script(self::JQUERY_JS_HANDLE);
            wp_enqueue_script(self::CLIPBOARD_JS_HANDLE);
            wp_enqueue_script('tooltipster');
            wp_enqueue_script('biocryptologylogin-script');
            wp_enqueue_script(self::JQUERY_VALIDATE_JS_HANDLE);
            wp_enqueue_script('form-validation');
        }
    }

    public function get_user_creation_url($url) {
        if (preg_match('/^' . preg_quote(get_site_url(), '/') . '/', $url)) {
            return $url;
        }

        return $this->default_user_creation_url;
    }
}

add_action('plugins_loaded', 'biocryptologylogin_init');

/**
 * Init the biocryptology class
 */
function biocryptologylogin_init() {
    $settings_api = new BiocryptologyLogin_Settings_API();

    return new BiocryptologyLogin($settings_api);
}
