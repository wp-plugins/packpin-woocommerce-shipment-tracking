<div style="clear:both;"></div>
<?php if (!$trackInfo): ?>
    <br>
    <br>
    <h2><?php echo __('No tracking info!', 'packpin'); ?></h2>
    <br>
<?php else: ?>
    <div class="pptrack-wrapper">
        <?php if (count($crossselling_ids_rand) > 0): ?>
            <h3><?php echo __('While you are waiting, we have something for you. Check offerings below:'); ?></h3>
            <div class="pptrack-crossells">
                <?php
                $list = implode(",", $crossselling_ids_rand);
                echo do_shortcode('[products ids="' . $list . '"]');
                ?>
            </div>
            <br/>
        <?php endif; ?>
        <?php if ($banner): ?>
            <?php if ($banner['url']): ?><a href="<?= $banner['url']; ?>"><?php endif; ?>
            <img src="<?= $banner['image']; ?>" alt="Banner"/>
            <?php if ($banner['url']): ?></a><?php endif; ?><br/>
        <?php endif; ?>
        <h2><?php echo __('Shipment Information', 'packpin'); ?></h2>
        <br/>

        <div class="pptrack-progress">
            <div class="pptrack-progress-bar-wrapper">
                <div class="pptrack-progress-bar-wrapper">
                    <ul class="pptrack-progress-bar <?php if ($trackInfo['status'] == 'delivered') echo "pptrack-progress-bar-done"; ?>">
                        <li class="<?php echo $statusHelper->getStatusClass('pending', $trackInfo['status']) ?>">
                            <div
                                class="pptrack-tracking-progress-bar-label"><?php echo __('Dispatched', 'packpin'); ?></div>
                            <span class="pptrack-progress-bubble"></span>
                        </li>
                        <li class="<?php echo $statusHelper->getStatusClass('in_transit', $trackInfo['status']) ?>">
                            <div
                                class="pptrack-tracking-progress-bar-label"><?php echo __('In Transit', 'packpin'); ?></div>
                            <span class="pptrack-progress-bubble"></span>
                        </li>
                        <li class="<?php echo $statusHelper->getStatusClass('out_for_delivery', $trackInfo['status']) ?>">
                            <div
                                class="pptrack-tracking-progress-bar-label"><?php echo __('Out for delivery', 'packpin'); ?></div>
                            <span class="pptrack-progress-bubble"></span>
                        </li>
                        <li class="<?php echo $statusHelper->getStatusClass('delivered', $trackInfo['status']) ?>">
                            <div
                                class="pptrack-tracking-progress-bar-label"><?php echo __('Delivered', 'packpin'); ?></div>
                            <span class="pptrack-progress-bubble"></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <br/>
        <br/>

        <div class="pptrack-tracking-info">
            <h2><?php echo __('Tracking Info', 'packpin'); ?></h2>

            <div class="pptrack-tracking-info-details">
                <?php $details = $trackInfo['track_details']; ?>
                <?php if (!$details) : ?>
                    <div class="pptrack-info-row">
                        <div class="pptrack-info-row-date"><?php echo $dateAdded ?>
                            <br/><?php echo $timeAdded ?></div>
                        <div class="pptrack-info-row-details-wrapper">
                            <div class="pptrack-info-row-details">
                                <?php echo __("Package prepared for dispatch", "packpin") ?>
                            </div>
                        </div>
                        <div style="clear:both;display:table;"></div>
                    </div>
                <?php else : ?>
                    <?php foreach ($details as $detail): ?>
                        <div class="pptrack-info-row">
                            <div>
                                <div class="pptrack-info-row-date"><?php echo $detail['event_date']; ?>
                                    <br/><?php echo $detail['event_time']; ?></div>
                                <div class="pptrack-info-row-details-wrapper">
                                    <div class="pptrack-info-row-details">
                                        <?php echo $detail['status_string']; ?>
                                        <?php if ($detail['address'] || $detail['country']): ?>
                                            <div
                                                class="pptrack-info-row-details-location"><?php echo $detail['address'] ?>
                                                , <?php echo $detail['country']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="clear:both;display:table;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="pptrack-tracking-general-info">
            <h2><?php echo __('Info', 'packpin'); ?></h2>

            <div class="pptrack-tracking-general-info-details">
                <div class="pptrack-tracking-row">
                    <span
                        class="pptrack-tracking-general-info-label"><?php echo __('Order number', 'packpin'); ?></span>
                    <span class="pptrack-tracking-general-info-value"><?php echo $order->get_order_number(); ?></span>
                    <br style="clear: both;"/>
                </div>
                <div class="pptrack-tracking-row">
                    <span
                        class="pptrack-tracking-general-info-label"><?php echo __('Tracking code', 'packpin'); ?></span>
                    <span class="pptrack-tracking-general-info-value"><?php echo $tableTrack['code']; ?></span>
                    <br style="clear: both;"/>
                </div>
                <div class="pptrack-tracking-row">
                    <span class="pptrack-tracking-general-info-label"><?php echo __('Shipped on', 'packpin'); ?></span>
                    <span class="pptrack-tracking-general-info-value"><?php echo $tableTrack["added"]; ?></span>
                    <br style="clear: both;"/>
                </div>
                <div class="pptrack-tracking-row">
                    <span class="pptrack-tracking-general-info-label"><?php echo __('Ships to', 'packpin'); ?></span>
                    <span
                        class="pptrack-tracking-general-info-value"><?php echo $order->get_formatted_shipping_address(); ?></span>
                    <br style="clear: both;"/>
                </div>

                <br/>

                <div class="pptrack-tracking-carrier-info-wrapper">
                    <span><?php echo __('For questions regarding your shipment contact carrier directly', 'packpin'); ?></span><br/>
                    <br/>

                    <div class="pptrack-tracking-courier-logo">
                        <img
                            src="https://button.packpin.com/assets/images/carriers_v2/<?php echo $tableTrack['carrier']; ?>.png"/>
                    </div>
                    <div class="pptrack-tracking-courier-info">
                        <h3><?php echo $carrier['name'] ?></h3>
                        <?php
                        $phone = $carrier['phone'];
                        $homepage = $carrier['homepage'];
                        ?>
                        <?php if ($phone) : ?>
                            <span><?php echo $phone ?></span><br/>
                        <?php endif; ?>
                        <?php if ($homepage) : ?>
                            <a href="<?php echo $homepage ?>" rel="nofollow" target="_blank"><?php echo $homepage ?></a>
                        <?php endif; ?>
                    </div>
                    <div style="clear:both;"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div style="clear:both;"></div>