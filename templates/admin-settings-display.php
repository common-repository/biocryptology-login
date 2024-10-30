<?php
/**
 * Provide a dashboard view for the plugin
 *
 * This file is used to markup the admin setting page
 *
 * @link       https://www.biocryptology.com
 * @since      1.0.0
 *
 * @package    biocryptologylogin
 * @subpackage biocryptologylogin/templates
 */

if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap">
    <div id="icon-options-general" class="icon32"></div>
    <h2><?php esc_html_e('Biocryptology Login', 'biocryptologylogin'); ?>: <?php esc_html_e('Configuration', 'biocryptologylogin'); ?></h2>
    <?php settings_errors(); ?>
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <!-- main content -->
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <div class="inside">
                            <?php
                            $this->settings_api->show_forms();
                            ?>
                            <input type="hidden"
                                   id="user_creation_url_txt"
                                   value="<?php echo $this->default_user_creation_url ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div> <!-- .wrap -->
