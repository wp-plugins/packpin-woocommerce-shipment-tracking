<?php
/**
 * Packpin Woocommerce Shipment Tracking
 *
 * Integrates Packpin tracking solution into your Wordpress & WooCommerce installation.
 *
 * @package   Packpin_Woocommerce_Shipment_Tracking
 * @author    Packpin <info@packpin.com>
 * @license   GPL-2.0+
 * @link      http://packpin.com
 * @copyright 2015 Packpin B.V.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('PackpinTracking_Admin_Settings')) {
    /**
     * Class PackpinTracking_Admin_Settings
     */
    class PackpinTracking_Admin_Settings
    {
        private $options;
        private $api;

        /**
         * Start up
         */
        public function __construct()
        {
            $this->options = get_option('packpin_tracking_settings');
            $this->api = new PackpinAPI();
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'settings_init'));
            add_action('admin_head', array($this, 'admin_register_head'));
        }

        /**
         * Add the menu to Wordpress menu tree
         */
        public function add_admin_menu()
        {
            add_options_page('Packpin', 'Packpin', 'manage_options', 'packpin_woocommerce_shipment_tracking', array($this, 'options_page'));
        }

        /**
         * Register the settings menu page
         */
        public function options_page()
        {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            include(sprintf("%s/templates/admin.php", dirname(__FILE__)));
        }

        /**
         * Initialize the settings fields
         */
        public function settings_init()
        {
            register_setting('pluginPage', 'packpin_tracking_settings', array($this, 'settings_validate'));

            if (empty($this->options['api_key']) || get_option('packpin_api_key_failure') == TRUE) {
                $this->settings_noapikey_init();
            } else {
                $this->settings_withapikey_init();
            }
        }

        /**
         * Setting up fields for settings.
         * This is used when API key is not set
         */
        public function settings_noapikey_init()
        {
            $key = $this->getOption('api_key');
            if (empty($key)) {
                add_settings_section(
                    'packpin_tracking_pluginPage_section',
                    __('Setup', 'packpin'),
                    array($this, 'intro_section_callback'),
                    'pluginPage'
                );
            } elseif (get_option('packpin_api_key_failure') == TRUE) {
                add_settings_section(
                    'packpin_tracking_pluginPage_section',
                    __('Setup failure!', 'packpin'),
                    array($this, 'apierror_callback'),
                    'pluginPage'
                );
            }

            $this->summon_api_field();
        }

        /**
         * Setting up fields for settings.
         * This is used when API key is set
         */
        public function settings_withapikey_init()
        {
            add_settings_section(
                'packpin_tracking_pluginPage_section',
                __('Settings', 'packpin'),
                array($this, 'settings_section_callback'),
                'pluginPage'
            );

            $this->summon_api_field();

            add_settings_field(
                'packpin_tracking_select_field_carriers',
                __('Used carriers', 'packpin'),
                array($this, 'render_settings_field'),
                'pluginPage',
                'packpin_tracking_pluginPage_section',
                array('type' => 'carriers')
            );

            add_settings_section(
                'packpin_tracking_pluginPage_cs_section',
                __('Cross-Selling Settings', 'packpin'),
                array($this, 'settings_cs_section_callback'),
                'pluginPage'
            );

            add_settings_field(
                'packpin_tracking_csswitch_field',
                __('State', 'packpin'),
                array($this, 'render_settings_field'),
                'pluginPage',
                'packpin_tracking_pluginPage_cs_section',
                array('type' => 'cs_switch')
            );

            add_settings_field(
                'packpin_tracking_csperpage_field',
                __('Items per page', 'packpin'),
                array($this, 'render_settings_field'),
                'pluginPage',
                'packpin_tracking_pluginPage_cs_section',
                array('type' => 'cs_perpage')
            );

            add_settings_field(
                'packpin_tracking_select_field_cs_banner',
                __('Banner', 'packpin'),
                array($this, 'render_settings_field'),
                'pluginPage',
                'packpin_tracking_pluginPage_cs_section',
                array('type' => 'cs_banner')
            );
        }

        /**
         * Callback for saving settings
         *
         * @param $input
         * @return array
         */
        public function settings_validate($input)
        {
            $output = array();
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $output[$key][] = strip_tags(stripslashes($v));
                    }
                } else {
                    if (isset($input[$key])) {
                        $output[$key] = strip_tags(stripslashes($input[$key]));
                    }
                }
            }

            if (preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $output['api_key'])) {
                // We have a good API key, let's test it.
                $api = new PackpinAPI($output['api_key']);
                $result = $api->testApiKey();

                if ($result && $result['body'] && $result['body'][0] == "ok") {
                    update_option('packpin_api_key_failure_msg', FALSE);
                    update_option('packpin_api_key_failure', FALSE);
                } else {
                    if ($result && $result['body'] && $result['body']['reason']) {
                        update_option('packpin_api_key_failure_msg', $result['body']['reason']);
                        update_option('packpin_api_key_failure', TRUE);
                    } else {
                        update_option('packpin_api_key_failure_msg', FALSE);
                        update_option('packpin_api_key_failure', FALSE);
                    }
                }

            } else {
                $output['api_key'] = "";
            }

            return $output;
        }


        /**
         * API Key field summoner
         */
        public function summon_api_field()
        {
            add_settings_field(
                'packpin_tracking_text_field_api',
                __('API Key *', 'packpin'),
                array($this, 'render_settings_field'),
                'pluginPage',
                'packpin_tracking_pluginPage_section',
                array('type' => 'api')
            );
        }

        /**
         * Callback for settings fields generation
         *
         * @param array $args
         */
        public function render_settings_field($args = [])
        {
            switch ($args['type']) {
                case 'api':
                    echo strtr('<input type="text" name="packpin_tracking_settings[api_key]" size="40" value="{val}"/>', array(
                        '{val}' => $this->getOption('api_key')
                    ));
                    break;
                case 'carriers':
                    $packpin_carrier_list = $this->api->getCarrierList();
                    $selected = ($this->getOption('carriers')) ? $this->getOption('carriers') : false; ?>
                    <select multiple size="20" name='packpin_tracking_settings[carriers][]'>
                        <?php foreach ($packpin_carrier_list['body'] as $c): ?>
                            <option
                                value="<?= $c['code']; ?>" <?php if ($selected) selected(true, in_array($c['code'], $selected)); ?>><?= $c['name']; ?></option>
                        <?php endforeach?>
                    </select>
                    <p class="description"
                       id="carriers-description"><?php echo __('If none of the above carriers are selected, all of them will be used.', 'packpin');?></p>
                    <?php
                    break;
                case 'cs_switch':
                    echo strtr('<select name="packpin_tracking_settings[crossselling_switch]">
                        <option value="0" {off}>Off</option>
                        <option value="products" {csp}>Cross-sell products</option>
                        <option value="banner" {bn}>Banner</option>
                    </select>', array(
                        '{off}' => selected($this->getOption('crossselling_switch'), 0, false),
                        '{csp}' => selected($this->getOption('crossselling_switch'), "products", false),
                        '{bn}' => selected($this->getOption('crossselling_switch'), "banner", false)
                    ));
                    break;
                case 'cs_perpage':
                    echo strtr('<input type="text" name="packpin_tracking_settings[crossselling_front_perpage]" size="40" value="{val}"/>', array(
                        '{val}' => $this->getOption('crossselling_front_perpage')
                    ));
                    break;
                case 'cs_banner':
                    if (function_exists('wp_enqueue_media')) {
                        wp_enqueue_media();
                    } else {
                        wp_enqueue_style('thickbox');
                        wp_enqueue_script('media-upload');
                        wp_enqueue_script('thickbox');
                    }?>
                    <p>
                        <strong><?php echo __('Image');?>:</strong><br/>
                        <img class="cs_banner" src="<?= $this->getOption('cs_banner_image'); ?>" height="100"/>
                        <input class="cs_banner_image" type="text" name="packpin_tracking_settings[cs_banner_image]"
                               size="60" value="<?= $this->getOption('cs_banner_image'); ?>">
                        <a href="#" class="cs_banner_upload"><?php echo __('Upload');?></a><br/>
                        <strong><?php echo __('URL');?>:</strong><br/>
                        <input class="cs_banner_url" type="text" name="packpin_tracking_settings[cs_banner_url]"
                               size="60" value="<?= $this->getOption('cs_banner_url'); ?>">
                    </p>
                    <?
                    break;
            }
        }

        /**
         * Settings default section callback
         */
        public function settings_section_callback()
        {
            echo __('Here you can configure the integration settings.', 'packpin');
        }

        /**
         * Settings cross-selling section callback
         */
        public function settings_cs_section_callback()
        {
            echo __('Here you can configure the cross-selling settings.', 'packpin');
        }

        /**
         * Settings intro section callback
         */
        public function intro_section_callback()
        {
            ?>
            In order to use all the features, please follow these steps:<br>
            <ol>
                <li>Sign up at <a href="https://panel.packpin.com" target="_blank">panel.packpin.com</a></li>
                <li>Go to <a href="https://panel.packpin.com/api_keys" target="_blank">"API keys"</a> and generate new
                    key
                </li>
                <li>Copy API key and paste to the field below</li>
                <li>Now the plugin is active and running</li>
            </ol>
        <?
        }

        /**
         * Settings api key error section callback
         */
        public function apierror_callback()
        {
            echo __('API Key is wrong!', 'packpin');
        }

        /**
         * Register CSS/JS for Wordpress admin
         */
        public function admin_register_head()
        {
            if ($_GET['page'] !== "packpin_woocommerce_shipment_tracking")
                return;

            $url = plugin_dir_url(__FILE__);
            echo '<link rel="stylesheet" href="' . $url . 'assets/css/admin.css" />';
            echo '<script src="' . $url . 'assets/js/admin.js' . '" /></script>';
        }

        /*
         * Getter for plugin options
         */
        private function getOption($name)
        {
            return (isset($this->options[$name])) ? $this->options[$name] : '';
        }
    }
}







