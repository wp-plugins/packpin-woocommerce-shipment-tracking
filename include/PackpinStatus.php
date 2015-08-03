<?php

class PackpinStatus
{
    /**
     * Get list of statuses with order number
     *
     * @return array
     */
    public function getOrderedStatusList()
    {
        return array(
            'pending' => 10,
            'no_info' => 10,
            'info_received' => 10,
            'in_transit' => 30,
            'to_overseas' => 30,
            'failed_attempt' => 30,
            'exception' => 30,
            'out_for_delivery' => 50,
            'delivered' => 60,
        );
    }

    /**
     * Get given status css class compared to current status
     *
     * @param $status
     * @param $current
     * @return string
     */
    public function getStatusClass($status, $current)
    {
        $list = $this->getOrderedStatusList();

        if (!isset($list[$status]) || !isset($list[$current]))
            return '';

        if ($list[$status] < $list[$current])
            return 'done';
        elseif ($list[$status] == $list[$current])
            return 'active';

        return '';
    }
}