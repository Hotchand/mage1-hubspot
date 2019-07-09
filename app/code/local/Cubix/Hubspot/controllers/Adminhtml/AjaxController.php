<?php
/**
 * Ajax controller for sending orders to HubSpot
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Adminhtml_AjaxController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Import order chunks
     *
     * @return void
     */
    public function indexAction()
    {
        $result = array();
        $result['success'] = false;
        $helper = Mage::helper('cubix_hubspot');
        try {
            $import = Mage::getModel('cubix_hubspot/import');
            $storeId = (int)$this->getRequest()->getParam('store_id');
            $chunkId = (int)$this->getRequest()->getParam('chunk_id');
			
			### import custom fields
			if($this->getRequest()->getParam('req_type') == 'import_customfields'){				
				$helper->callCustomFieldsBatchApi($storeId);
			}
			
			### import customers
			if($this->getRequest()->getParam('req_type') == 'import_customers'){
				$websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
				// Get customers from the Database
				$customers = $import->getCustomers($websiteId, $chunkId);
				
				// Send orders via API helper method				
				$helper->callCustomerBatchApi($storeId, $customers);
			}
			
			### import orders
			if($this->getRequest()->getParam('req_type') == 'import_orders'){
				// Get orders from the Database
				$orders = $import->getOrders($storeId, $chunkId);
				// Send orders via API helper method				
				$helper->callBatchApi($storeId, $orders);
			}
            $result['success'] = true;
        } catch (Exception $e) {
            Mage::logException($e);
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
