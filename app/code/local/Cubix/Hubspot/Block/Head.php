<?php
/**
 * Block for head part who render all js lines
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Block_Head extends Mage_Core_Block_Template
{
    /**
     * key in session storage
     */
    const DATA_TAG = "cubix_events";

    /**
     * Get events to track them to metrilo js api
     *
     * @return array
     */
    public function getEvents()
    {
        $helper = Mage::helper('cubix_hubspot');
        $events = (array)Mage::getSingleton('core/session')->getData(self::DATA_TAG);
        // clear events from session ater get events once
        Mage::getSingleton('core/session')->setData(self::DATA_TAG,'');
        return array_filter($events);
    }

    /**
     * Render metrilo js if module is enabled
     *
     * @return string
     */
    protected function _toHtml()
    {
        $html = parent::_toHtml();
        $helper = Mage::helper('cubix_hubspot');

        $request = Mage::app()->getRequest();
        $storeId = $helper->getStoreId($request);

        if($helper->isEnabled($storeId))
            return $html;
    }
}
