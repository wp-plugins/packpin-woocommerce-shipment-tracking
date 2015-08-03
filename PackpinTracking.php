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
 *
 * @wordpress-plugin
 * Plugin Name:       Packpin Woocommerce Shipment Tracking
 * Plugin URI:        https://wordpress.org/plugins/packpin_woocommerce_shipment-tracking/
 * Description:       Integrates Packpin tracking solution into your Wordpress & WooCommerce installation
 * Version:           1.0
 * Author:            Packpin <info@packpin.com>
 * Author URI:        http://packpin.com
 * Text Domain:       packpin-woocommerce-shipment-tracking
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /lang
 */

define ('PPWSI_VERSION', '1.0');
define ('PPWSI_DB_VERSION', '1.0');

/**
 * Security
 */
if (!defined('ABSPATH')) exit;

/**
 * Check if WooCommerce is active
 **/
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (!class_exists('PackpinTracking')) {
    /**
     * class PackpinTracking
     */
    final class PackpinTracking
    {
        /**
         * Constructor
         */
        public function __construct()
        {
            global $wpdb;

            $this->requirements();

            $this->api = new PackpinAPI();
            $this->options = get_option('packpin_tracking_settings');
            $this->track_table_name = $wpdb->prefix . "pptrack_codes";
            $this->track_page_slug = 'pptrack';

            add_action('add_meta_boxes', array(&$this, 'add_meta_box'));
            add_action('woocommerce_process_shop_order_meta', array(&$this, 'save_meta_box'), 0, 2);
            add_action('woocommerce_view_order', array(&$this, 'display_tracking_info'));
            add_action('woocommerce_email_before_order_table', array(&$this, 'email_display'));
            add_action('init', array(&$this, 'register_shipped_order_status'));
            add_filter('wc_order_statuses', array(&$this, 'add_shipped_order_statuses'));
            add_filter('woocommerce_locate_template', array(&$this, 'woocommerce_locate_template'), 10, 3);
            add_action('admin_notices', array(&$this, 'admin_notice'));
            add_action('admin_init', array(&$this, 'admin_notice_ignore'));
            add_action('init', array(&$this, 'initiate_woocommerce_email'));
            add_filter('woocommerce_email_classes', array(&$this, 'add_shipped_order_woocommerce_email'));

            register_activation_hook(__FILE__, array($this, 'install'));
        }

        /**
         * Initialize various components for the plugin
         */
        public function requirements()
        {
            require_once "include/PackpinAPI.php";
            require_once "include/PackpinStatus.php";
            require_once "PackpinTracking_Admin_Settings.php";
            require_once "PackpinTracking_Front_Shortcode.php";

            $PackpinTracking_Admin_Settings = new PackpinTracking_Admin_Settings();
            $PackpinTracking_Front_Shortcode = new PackpinTracking_Front_Shortcode();
        }

        /**
         * Installation callback
         */
        public function install()
        {
            global $wpdb;

            // Insert pptrack page
            // Delete old one just in case there's something
            $ppTrack = $this->get_id_by_slug($this->track_page_slug);
            if ($ppTrack)
                wp_delete_post($ppTrack, true);

            $page = array(
                'post_content' => '[pptrack_output]',
                'post_name' => $this->track_page_slug,
                'post_title' => 'Track your order',
                'post_status' => 'Publish',
                'post_type' => 'page',
                'ping_status' => 'closed',
                'comment_status' => 'closed'
            );
            wp_insert_post($page);

            // Create/update db table
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$this->track_table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                code tinytext NOT NULL,
                carrier tinytext NOT NULL,
                codehash tinytext NOT NULL,
                additional longtext NOT NULL,
                post_id bigint(20) NOT NULL,
                added datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY id (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            add_option('pptrack_db_version', PPWSI_DB_VERSION);
        }

        /**
         * Add the meta box for shipment info on the order page
         *
         * @access public
         */
        public function add_meta_box()
        {
            add_meta_box('Packpin_WooCommerce', __('Packpin', 'packpin'), array(&$this, 'meta_box'), 'shop_order', 'side', 'high');
        }

        /**
         * Show the meta box for shipment info on the order page
         *
         * @access public
         */
        public function meta_box()
        {
            // just draw the layout, no data
            global $post;

            $selected_carrier = get_post_meta($post->ID, '_packpin_carrier', true);

            $packpin_carrier_list = $this->api->getCarrierList();
            $selected = $this->options['carriers'];

            $toShow = (count($selected) > 0) ? array_values(array_filter($packpin_carrier_list['body'], function ($k) use ($selected) {
                return in_array($k['code'], $selected);
            })) : $packpin_carrier_list['body'];

            echo '<div id="packpin_wrapper">';

            echo '<p class="form-field"><label for="packpin_carrier">' . __('Carrier:', 'packpin') . '</label><br/><select id="packpin_carrier" name="packpin_carrier" class="chosen_select" style="width:100%">';
            $selected_text = (empty($selected_carrier)) ? 'selected="selected"' : "";
            echo '<option disabled ' . $selected_text . ' value="">Please Select</option>';
            foreach ($toShow as $c) {
                echo strtr('<option value="{c}"{s}>{n}</option>', [
                    '{c}' => $c['code'],
                    '{n}' => $c['name'],
                    '{s}' => ($selected_carrier == $c['code']) ? ' selected="selected"' : ''
                ]);
            }
            echo '</select>';

            $packpin_code = get_post_meta($post->ID, '_packpin_code', true);
            woocommerce_wp_text_input(array(
                'id' => 'packpin_code',
                'label' => __('Tracking Code', 'packpin'),
                'placeholder' => "Enter tracking code",
                'class' => 'packpin-code',
                'value' => $packpin_code
            ));

            $hash = get_post_meta($post->ID, '_packpin_hash', true);
            if (!empty($hash)) {
                echo '<a href="'.get_permalink($this->get_id_by_slug('pptrack')).'?h=' . $hash . '" target="_blank">See tracking status</a>';
                woocommerce_wp_hidden_input(array(
                    'id' => 'packpin_hash',
                    'value' => $hash
                ));
            }

            woocommerce_wp_hidden_input(array(
                'id' => 'packpin_code_old',
                'value' => $packpin_code
            ));

            woocommerce_wp_hidden_input(array(
                'id' => 'packpin_carrier_old',
                'value' => $selected_carrier
            ));
            echo '</div>';
        }

        /**
         * Callback for order page meta box
         *
         * @param $post_id
         * @param $post
         */
        public function save_meta_box($post_id, $post)
        {
            global $wpdb;

            if (isset($_POST['packpin_code'])) {
                if ($_POST['packpin_code'] == $_POST['packpin_code_old'] && $_POST['packpin_carrier'] == $_POST['packpin_carrier_old'])
                    return;

                $packpin_carrier = wc_clean($_POST['packpin_carrier']);
                update_post_meta($post_id, '_packpin_carrier', $packpin_carrier);

                $packpin_code = wc_clean($_POST['packpin_code']);
                update_post_meta($post_id, '_packpin_code', $packpin_code);

                $hash = $_POST['packpin_hash'];
                if (!empty($hash)) {
                    // Update
                    $res = $this->api->addTrackingCode($packpin_carrier, $packpin_code);
                    $wpdb->update(
                        $this->track_table_name,
                        array(
                            'code' => $packpin_code,
                            'carrier' => $packpin_carrier,
                            'additional' => json_encode($res),
                            'updated' => current_time('mysql')
                        ),
                        array('codehash' => $hash),
                        array(
                            '%s', '%s', '%s', '%s'
                        ),
                        array('%s')
                    );
                } else {
                    //Insert new
                    $hash = $this->_generateHash();
                    update_post_meta($post_id, '_packpin_hash', $hash);

                    $res = $this->api->addTrackingCode($packpin_carrier, $packpin_code);

                    $wpdb->insert(
                        $this->track_table_name,
                        array(
                            'code' => $packpin_code,
                            'carrier' => $packpin_carrier,
                            'codehash' => $hash,
                            'additional' => json_encode($res),
                            'post_id' => $post_id,
                            'added' => current_time('mysql'),
                            'updated' => current_time('mysql')
                        ),
                        array(
                            '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                        )
                    );
                }

                $order = new WC_Order($post_id);
                $order->update_status('shipped', 'order_note');
            }
        }

        /**
         * Callback for showing order tracking page link
         * TODO : Show more info about the order
         *
         * @param $order_id
         */
        public function display_tracking_info($order_id)
        {
            global $wpdb;

            $tbl = $wpdb->prefix . "pptrack_codes";
            $tableTrack = $wpdb->get_row("SELECT * FROM $tbl WHERE post_id = '$order_id'", ARRAY_A);
            if (!$tableTrack)
                return;

            echo strtr('<a href="{u}">{l}</a>', [
                '{u}' => get_permalink($this->get_id_by_slug('pptrack')) . '?h=' . $tableTrack['codehash'],
                '{l}' => __('See your order tracking info', 'packpin')
            ]);
        }

        /**
         * Initialize WC Mailer class
         */
        public function initiate_woocommerce_email()
        {
            // Just when you update the order_status on backoffice
            if (isset($_POST['order_status'])) {
                WC()->mailer();
            }
        }

        /**
         * Initialize mail type
         *
         * @param $email_classes
         * @return mixed
         */
        public function add_shipped_order_woocommerce_email($email_classes)
        {
            require_once('include/WC_Shipped_Order_Email.php');
            $email_classes['WC_Shipped_Order_Email'] = new WC_Shipped_Order_Email();
            return $email_classes;
        }

        /**
         * Callback for displaying order tracking page link in email
         *
         * @param $order
         */
        public function email_display($order)
        {
            $this->display_tracking_info($order->id);
        }

        /**
         * Initialize new Wordpress status
         */
        public function register_shipped_order_status()
        {
            register_post_status('wc-shipped', array(
                'label' => 'Shipped',
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
            ));
        }

        /**
         * Add the Wordpress based status to WooCommerce
         *
         * @param $order_statuses
         * @return array
         */
        public function add_shipped_order_statuses($order_statuses)
        {
            $new_order_statuses = array();
            foreach ($order_statuses as $key => $status) {

                $new_order_statuses[$key] = $status;

                if ('wc-processing' === $key) {
                    $new_order_statuses['wc-shipped'] = 'Shipped';
                }
            }

            return $new_order_statuses;
        }

        /**
         * Initialize Admin notice after installation of plugin
         */
        public function admin_notice()
        {
            global $current_user;
            $user_id = $current_user->ID;
            /* Check that the user hasn't already clicked to ignore the message */
            if ($_GET['page'] == "packpin_woocommerce_shipment_tracking")
                return;

            if (!empty($options['api_key']))
                return;

            if (!get_user_meta($user_id, 'dismiss_pptrack_woo')) {
                echo '<div class="updated"><p>';
                add_user_meta($user_id, 'dismiss_pptrack_woo', 'true', true);
                printf(__('Thanks for installing Packpin plugin for WooCommerce!<br/>To start using Packpin tracking functionality, please go to <a href="%1$s">Settings</a> page and configure your API key, or you can <a href="%1$s">hide this notice</a> for the time being.'), 'options-general.php?page=packpin_woocommerce_shipment_tracking', '?dismiss_pptrack_woo=0');
                echo "</p></div>";
            }
        }

        /**
         * Callback to dismiss the admin notice
         */
        function admin_notice_ignore()
        {
            global $current_user;
            $user_id = $current_user->ID;
            /* If user clicks to ignore the notice, add that to their user meta */
            if (isset($_GET['dismiss_pptrack_woo']) && '0' == $_GET['dismiss_pptrack_woo']) {
                add_user_meta($user_id, 'dismiss_pptrack_woo', 'true', true);
            }
        }

        /**
         * Helper function to override template placement
         *
         * @param $template
         * @param $template_name
         * @param $template_path
         * @return string
         */
        function woocommerce_locate_template($template, $template_name, $template_path)
        {
            global $woocommerce;

            $_template = $template;

            if (!$template_path) $template_path = $woocommerce->template_url;

            $plugin_path = $this->plugin_path() . '/woocommerce/';

            // Look within passed path within the theme - this is priority
            $template = locate_template(
                array(
                    $template_path . $template_name,
                    $template_name
                )
            );

            // Modification: Get the template from this plugin, if it exists
            if (!$template && file_exists($plugin_path . $template_name))
                $template = $plugin_path . $template_name;

            // Use default template
            if (!$template)
                $template = $_template;

            // Return what we found
            return $template;
        }

        /**
         * Helper function to get plugin path
         *
         * @return string
         */
        private function plugin_path()
        {
            return untrailingslashit(plugin_dir_path(__FILE__));
        }

        /**
         * Helper function to get page ID by slug
         *
         * @param $page_slug
         * @return int|null
         */
        private function get_id_by_slug($page_slug)
        {
            $page = get_page_by_path($page_slug);
            if ($page) {
                return $page->ID;
            } else {
                return null;
            }
        }

        /**
         * Generate valid UUID v4 hash
         *
         * @return string
         */
        private function _generateHash()
        {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }

    /**
     * Register this class globally (??)
     */
    $GLOBALS['PackpinTracking'] = new PackpinTracking;

}