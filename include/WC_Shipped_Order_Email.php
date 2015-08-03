<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * A custom Shipped Order WooCommerce Email class
 *
 * @since 0.1
 * @extends \WC_Email
 */
class WC_Shipped_Order_Email extends WC_Email
{
    /**
     * Set email defaults
     *
     * @since 0.1
     */
    public function __construct()
    {
        // set ID, this simply needs to be a unique name
        $this->id = 'wc_shipped_order';
        // this is the title in WooCommerce Email settings
        $this->title = 'Shipped Order';
        // this is the description in WooCommerce email settings
        $this->description = 'Shipped Order Notification emails are sent when admin adds a tracking code';
        // these are the default heading and subject lines that can be overridden using the settings
        $this->heading = 'Shipping for Order #{order_number}';
        $this->subject = 'Shipping for Order #{order_number}';
        // these define the locations of the templates that this email should use, we'll just use the new order template since this email is similar
        $this->template_html = 'emails/customer-shipping-order.php';
        $this->template_plain = 'emails/plain/customer-shipping-order.php';
        $this->template_base = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/';

        // Trigger on new paid orders
        add_action('woocommerce_order_status_shipped', array($this, 'trigger'));
        // Trigger on new paid orders
        //add_action( 'woocommerce_order_status_pending_to_shipped', array( $this, 'trigger' ) );
        //add_action( 'woocommerce_order_status_processing_to_shipped',  array( $this, 'trigger' ) );

        //add_action( 'woocommerce_order_status_failed_to_processing_notification',  array( $this, 'trigger' ) );

        // Call parent constructor to load any other defaults not explicity defined here

        parent::__construct();
    }

    /**
     * Determine if the email should actually be sent and setup email merge variables
     *
     * @since 0.1
     * @param int $order_id
     */
    public function trigger($order_id)
    {
        // bail if no order ID is present
        if (!$order_id)
            return;

        // setup order object
        $this->object = new WC_Order($order_id);

        $this->recipient = $this->object->billing_email;

        if (!$this->recipient) {
            error_log('No email recipient for WooCommerce Order #' . $order_id);
            return;
        }

        // replace variables in the subject/headings
        $this->find[] = '{order_date}';
        $this->replace[] = date_i18n(wc_date_format(), strtotime($this->object->order_date));
        $this->find[] = '{order_number}';
        $this->replace[] = $this->object->get_order_number();

        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }

        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    /**
     * get_content_html function.
     *
     * @since 0.1
     * @return string
     */
    public function get_content_html()
    {
        ob_start();
        wc_get_template($this->template_html, array(
            'order' => $this->object,
            'email_heading' => $this->get_heading()
        ));
        return ob_get_clean();
    }

    /**
     * get_content_plain function.
     *
     * @since 0.1
     * @return string
     */
    public function get_content_plain()
    {
        ob_start();
        wc_get_template($this->template_plain, array(
            'order' => $this->object,
            'email_heading' => $this->get_heading()
        ));
        return ob_get_clean();
    }

    /**
     * Initialize Settings Form Fields
     *
     * @since 2.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable this email notification',
                'default' => 'yes'
            ),
            'subject' => array(
                'title' => 'Subject',
                'type' => 'text',
                'description' => sprintf('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', $this->subject),
                'placeholder' => '',
                'default' => ''
            ),
            'heading' => array(
                'title' => 'Email Heading',
                'type' => 'text',
                'description' => sprintf(__('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.'), $this->heading),
                'placeholder' => '',
                'default' => ''
            ),
            'email_type' => array(
                'title' => 'Email type',
                'type' => 'select',
                'description' => 'Choose which format of email to send.',
                'default' => 'html',
                'class' => 'email_type',
                'options' => array(
                    'plain' => __('Plain text', 'woocommerce'),
                    'html' => __('HTML', 'woocommerce'),
                    'multipart' => __('Multipart', 'woocommerce'),
                )
            )
        );
    }
}