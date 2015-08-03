<div class="wrap">
    <form action="options.php" method="post">
        <h2><?php echo __('Packpin Woocommerce Shipment Tracking', 'packpin'); ?></h2>

        <div class="packpinLogo">
            <img class="logo" src="https://packpin.com/wp-content/themes/packpinv2/images/packpinLogo.svg" width="150">
        </div>
        <?php
        settings_fields('pluginPage');
        do_settings_sections('pluginPage');
        submit_button();
        ?>
    </form>
</div>