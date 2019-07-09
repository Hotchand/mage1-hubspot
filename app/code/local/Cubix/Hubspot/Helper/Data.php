<?php
/**
 * Helper class for Hubspot properties
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
     * Get session instance
     *
     * @return Mage_Core_Model_Session
     */
    public function getSession()
    {
        return Mage::getSingleton('core/session');
    }
	
	
	/**
    * Get storeId for the current request context
    *
    * @return string
    */
    public function getStoreId($request = null) {
        if ($request) {
            # If request is passed retrieve store by storeCode
            $storeCode = $request->getParam('store');
            if ($storeCode) {
                return Mage::getModel('core/store')->load($storeCode)->getId();
            }
        }
        # If no request or empty store code
        return Mage::app()->getStore()->getId();
    }
	
	
	/**
     * Check if metrilo module is enabled
     *
     * @return boolean
     */
    public function isEnabled($storeId = null)
    {
        return Mage::getStoreConfig('cubix_hubspot_sec/cubix_hubspot_group/cubix_hubspot_enable', $storeId);
    }
	
	
	/**
     * Get API ID from system configuration
     *
     * @return string
     */
    public function getApiId($storeId = null)
    {
        return Mage::getStoreConfig('cubix_hubspot_sec/cubix_hubspot_group/cubix_hubspot_appid', $storeId);
    }
	/**
     * Get API Client ID from system configuration
     *
     * @return string
     */
    public function getApiClientId($storeId = null)
    {
        return Mage::getStoreConfig('cubix_hubspot_sec/cubix_hubspot_group/cubix_hubspot_clientid', $storeId);
    }
    /**
     * Get API Secret from system configuration
     *
     * @return string
     */
    public function getApiSecret($storeId = null)
    {
        return Mage::getStoreConfig('cubix_hubspot_sec/cubix_hubspot_group/cubix_hubspot_clientsecret', $storeId);
    }
	
	/**
     * Get API hapikey from system configuration
     *
     * @return string
     */
    public function getApiHapiKey($storeId = null)
    {
        return Mage::getStoreConfig('cubix_hubspot_sec/cubix_hubspot_group/cubix_hubspot_hapikey', $storeId);
    }
	
	/**
     * Get order details and sort them for Hubspot
     *
     * @param  Mage_Sales_Model_Order $order
     * @return array
     */
    public function prepareOrderDetails($order)
    {
        
		$storeCode = Mage::getSingleton('adminhtml/config_data')->getScope();
		
		$suffix = Mage::getStoreConfig('catalog/seo/product_url_suffix');
		$data = array(
            'order_id'          => $order->getIncrementId(),
            'order_status'      => $order->getStatus(),
            'amount'            => (float)$order->getGrandTotal(),
            'shipping_amount'   => (float)$order->getShippingAmount(),
            'tax_amount'        => $order->getTaxAmount(),
            'items'             => array(),
            'shipping_method'   => $order->getShippingDescription(),
            'payment_method'    => $order->getPayment()->getMethodInstance()->getTitle(),
			'site_info'			=> $storeCode.'-'. Mage::getModel('core/store')->load($storeCode)->getName()
        );
        $this->_assignBillingInfo($data, $order);
        if ($order->getCouponCode()) {
            $data['coupons'] = array($order->getCouponCode());
        }
        $skusAdded = array();
        foreach ($order->getAllItems() as $item) {
            if (in_array($item->getSku(), $skusAdded)) continue;
            $skusAdded[] = $item->getSku();
			###				
			$productUrl = Mage::getUrl().$item->getProduct()->getUrlKey().$suffix;
			###
            $dataItem = array(
                'id'        => $item->getProductId(),
                'price'     => (float)$item->getPrice() ? $item->getPrice() : $item->getProduct()->getFinalPrice(),
                'name'      => $item->getName(),
                'url'       => $productUrl,                 
				'quantity'  => (int)$item->getQtyOrdered()
            );
            if ($item->getProductType() == 'configurable' || $item->getProductType() == 'grouped') {
                if ($item->getProductType() == 'grouped') {
                    $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($item->getProductId());
                    $parentId = $parentIds[0];
                } else {
                    $parentId = $item->getProductId();
                }
                $mainProduct = Mage::getModel('catalog/product')->load($parentId);
				###				
    			$productUrl = Mage::getUrl().$mainProduct->getUrlKey().$suffix;
				###
                $dataItem['id']     = $mainProduct->getId();
                $dataItem['name']   = $mainProduct->getName();
                $dataItem['url']    = $productUrl;
                $dataItem['option_id'] = $item->getSku();
                $dataItem['option_name'] = trim(str_replace("-", " ", $item->getName()));
                $dataItem['option_price'] = (float)$item->getPrice();
            }
            $data['items'][] = $dataItem;
        }
        return $data;
    }
	
	/**
     * Create submition ready arrays from Array of Mage_Sales_Model_Order
     * @param Array(Mage_Sales_Model_Order) $orders
     * @return Array of Arrays
     */
    private function _buildOrdersForSubmition($orders) {
        $ordersForSubmition = array();

        foreach ($orders as $order) {
            if ($order->getId()) {
                array_push($ordersForSubmition, $this->_buildOrderForSubmition($order));
            }
        }

        return $ordersForSubmition;
    }
	
	private function _buildOrderForSubmition($order) {
        $orderDetails = $this->prepareOrderDetails($order);
        // initialize additional params
        $callParameters = false;
        // check if order has customer IP in it
        $ip = $order->getRemoteIp();
        if ($ip) {
            $callParameters = array('use_ip' => $ip);
        }
        // initialize time
        $time = false;
        if ($order->getCreatedAtStoreDate()) {
            $time = $order->getCreatedAtStoreDate()->getTimestamp() * 1000;
        }

        $identityData = $this->_orderIdentityData($order);

        return $this->_buildEventArray(
            $identityData['email'], 'order', $orderDetails, $identityData, $time, $callParameters
        );
    }
	
	private function _orderIdentityData($order) {
        return array(
            'email'         => $order->getCustomerEmail(),
            'first_name'    => $order->getBillingAddress()->getFirstname(),
            'last_name'     => $order->getBillingAddress()->getLastname(),
            'name'          => $order->getBillingAddress()->getName(),
        );
    }

    private function _buildCall($storeId, $ordersForSubmition) {
        return array(
            'token'    => $this->getApiHapiKey($storeId),
            'events'   => $ordersForSubmition,
            // for debugging/support purposes
            'platform' => 'Magento ' . Mage::getEdition() . ' ' . Mage::getVersion(),
            'version'  => (string)Mage::getConfig()->getModuleConfig("Cubix_Hubspot")->version
        );
    }

    private function _assignBillingInfo(&$data, $order)
    {
        $billingAddress = $order->getBillingAddress();
        # Assign billing data to order data array
        $data['billing_phone']    = $billingAddress->getTelephone();
        $data['billing_country']  = $billingAddress->getCountryId();
        $data['billing_region']   = $billingAddress->getRegion();
        $data['billing_city']     = $billingAddress->getCity();
        $data['billing_postcode'] = $billingAddress->getPostcode();
        $data['billing_address']  = $billingAddress->getStreetFull();
        $data['billing_company']  = $billingAddress->getCompany();
    }
	
	
	/**
     * Create HTTP request to HubSpot API to sync multiple orders
     *
     * @param Array(Mage_Sales_Model_Order) $orders
     * @return void
     */
    public function callBatchApi($storeId, $orders)
    {
        try {
            $ordersForSubmition = $this->_buildOrdersForSubmition($orders);
            $call = $this->_buildCall($storeId, $ordersForSubmition);

            $this->_callHubspotApiAsync($storeId, $call);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'Cubix_Hubspot.log');
        }
    }
	
	// Private functions start here
    private function _callHubspotApiAsync($storeId, $call) {
        ksort($call);

        $basedCall = base64_encode(Mage::helper('core')->jsonEncode($call));
        $signature = trim($this->getApiHapiKey($storeId)); 
        
        $requestBody = array(
            's'   => $signature,
            'hs'  => $basedCall
        );
		if($this->isEnabled($storeId) and $signature != '')
		{
			$hubspotApi = Mage::helper('cubix_hubspot/hubspot');
			$orders_data = array();
			$customer_data = array();
			$all_customers = $hubspotApi->getAllContactsArray($signature);
			$allDeals = $hubspotApi->getAllDeals($signature);
			//create custom fields/properties of deals for order data
			$this->CreateCustomField($storeId);
			if(!empty($call)){
				
				if(isset($call['events']) and !empty($call['events'])){
					$i=0;
					
					foreach($call['events'] as $call_data){
						
						$customer_data['firstname'] 	= $call_data['identity']['first_name'];
						$customer_data['lastname'] 		= $call_data['identity']['last_name'];
						$customer_data['email'] 		= $call_data['identity']['email'];
						$customer_data['phone'] 		= $call_data['params']['billing_phone'];
						$customer_data['country'] 		= $call_data['params']['billing_country'];
						$customer_data['state'] 		= $call_data['params']['billing_region'];
						$customer_data['city'] 			= $call_data['params']['billing_city'];
						$customer_data['zip'] 			= $call_data['params']['billing_postcode'];
						$customer_data['address'] 		= $call_data['params']['billing_address'];
						$customer_vid = 0; 
						$portal_id = 0;
						if($email_exist = $this->get_email($all_customers, $customer_data['email'])){
							$customer_vid = $email_exist['vid'];
							$portal_id =$email_exist['portal_id'];
						}else if ($email_exists = $hubspotApi->find_ContactByEmail($signature, $customer_data['email']) and $email_exists['status_code'] == 200){
							$customer_vid = $email_exists['response']->vid;
							$portal_id =$email_exists['response']->{'portal-id'};
						}else{
							$customer_res = $hubspotApi->create_contact($signature, $customer_data);
							if($customer_res['status_code'] == 200){
								$customer_vid = $customer_res['response']->vid;
								$portal_id = $customer_res['response']->{'portal-id'};
							}
						}
						
						$deal_info['dealname']				= $call_data['params']['order_id'].' - '.$customer_data['firstname'].' '.$customer_data['lastname'];
						$deal_info['portalId']				= $portal_id;
						$deal_info['sender_vid']			= $customer_vid;
						
						$i3 = 5;
						$orders_data = array();
						$orders_data[$i3]['name'] 			= 'order_id';			
						$orders_data[$i3]['value']			= $call_data['params']['order_id'];
						$i3++;
						$orders_data[$i3]['name']= 'order_status';
						$orders_data[$i3]['value']		= $call_data['params']['order_status'];
						$i3++;
						$orders_data[$i3]['name']				= 'amount';
						$orders_data[$i3]['value']				= $call_data['params']['amount'];
						$i3++;
						$orders_data[$i3]['name']		= 'shipping_amount';
						$orders_data[$i3]['value']		= $call_data['params']['shipping_amount'];
						$i3++;
						$orders_data[$i3]['name']			= 'tax_amount';
						$orders_data[$i3]['value']			= $call_data['params']['tax_amount'];
						$i3++;
						$orders_data[$i3]['name'] 		= 'billing_phone';
						$orders_data[$i3]['value'] 		= $call_data['params']['billing_phone'];
						$i3++;
						$orders_data[$i3]['name'] 	= 'billing_country';
						$orders_data[$i3]['value'] 	= $call_data['params']['billing_country'];
						
						if(isset($call_data['params']['billing_region'])){
							$i3++;
							$orders_data[$i3]['name'] 	= 'billing_region';
							$orders_data[$i3]['value'] 	= $call_data['params']['billing_region'];
						}
						$i3++;
						$orders_data[$i3]['name'] 		= 'billing_city';
						$orders_data[$i3]['value'] 		= $call_data['params']['billing_city'];
						$i3++;
						$orders_data[$i3]['name'] 	= 'billing_postcode';
						$orders_data[$i3]['value'] 	= $call_data['params']['billing_postcode'];
						$i3++;
						$orders_data[$i3]['name'] 	= 'billing_address';
						$orders_data[$i3]['value'] 	= $call_data['params']['billing_address'];
						
						if(isset($call_data['use_ip'])){
							$i3++;
							$orders_data[$i3]['name'] 			= 'use_ip';
							$orders_data[$i3]['value'] 			= $call_data['use_ip'];
						}
						$i3++;
						$orders_data[$i3]['name'] 		= 'order_server_time';
						$orders_data[$i3]['value'] 		= $call_data['server_time'];
						$i3++;
						$orders_data[$i3]['name'] 			= 'order_time';
						$orders_data[$i3]['value']			= $call_data['time'];
						
						
						#### product(s) purchased details
						$i2 = 1;
						foreach($call_data['params']['items'] as $item2){
							$i3++;
							$orders_data[$i3]['name']	=	'item_'.$i2.'_id';
							$orders_data[$i3]['value'] 		= $item2['id'];
							$i3++;
							$orders_data[$i3]['name']	= 'item_'.$i2.'_url';
							$orders_data[$i3]['value']	= $item2['url'];
							$i3++;
							$orders_data[$i3]['name']	= 'item_'.$i2.'_quantity';
							$orders_data[$i3]['value'] 	= $item2['quantity'];
							
							if(isset($item['option_id'])){
								$i3++;
								$orders_data[$i3]['name']	=  'item_'.$i2.'_sku';
								$orders_data[$i3]['value'] 	= $item2['option_id'];
							}
							
							if(isset($item['option_name'])){
								$i3++;
								$orders_data[$i3]['name']	= 'item_'.$i2.'_option_name';
								$orders_data[$i3]['value']	= $item2['option_name'];
							}
							
							if(isset($item['name'])){
								$i3++;
								$orders_data[$i3]['name']	= 'item_'.$i2.'_name';
								$orders_data[$i3]['value'] 	= $item2['name'];
							}
							
							if(isset($item['option_price'])){
								$i3++;
								$orders_data[$i3]['name']	= 'item_'.$i2.'_option_price';
								$orders_data[$i3]['value'] 	= $item2['option_price'];
							}
							
							if(isset($item['price'])){
								$i3++;
								$orders_data[$i3]['name'] 	='item_'.$i2.'_price';
								$orders_data[$i3]['value'] 	= $item2['price'];
							}
							
							$i2++;
						}
						//check if order already not created
						if(!$this->getDealName($allDeals, $deal_info['dealname'])){
							$deal_res = $hubspotApi->create_deal($signature, $deal_info,$orders_data);
						}						
					    $i++;						 
					}					
						
				}
			}
			
			 
		}else{
			
		}
		
    }
	
	 /**
     * Build event array ready for encoding and encrypting. Built array is returned using ksort.
     *
     * @param  string  $ident
     * @param  string  $event
     * @param  array  $params
     * @param  boolean|array $identityData
     * @param  boolean|int $time
     * @param  boolean|array $callParameters
     * @return void
     */
    private function _buildEventArray($ident, $event, $params, $identityData = false, $time = false, $callParameters = false)
    {
        $call = array(
            'event_type'    => $event,
            'params'        => $params,
            'uid'           => $ident
        );

        if($time) {
            $call['time'] = $time;
        }

        $call['server_time'] = round(microtime(true) * 1000);
        // check for special parameters to include in the API call
        if($callParameters) {
            if($callParameters['use_ip']) {
                $call['use_ip'] = $callParameters['use_ip'];
            }
        }
        // put identity data in call if available
        if($identityData) {
            $call['identity'] = $identityData;
        }
        // Prepare keys is alphabetical order
        ksort($call);

        return $call;
    }
	
	############# Import Customers to Hubspot using API #########
	/**
     * Create HTTP request to Hubspot API to sync multiple customers
     *
     * @param Array(Mage_Sales_Model_Customer) $customers
     * @return void
     */
    public function callCustomerBatchApi($storeId, $customers)
    {        
		try {
            $customersForSubmition = $this->_buildCustomersForSubmition($customers);
            $call = $this->_buildCustomersCall($storeId, $customersForSubmition);
            $this->_callCustomerHubspotApiAsync($storeId, $call);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'Cubix_Hubspot_Customer.log');
        }
    }
	
	// Private functions start here
    private function _callCustomerHubspotApiAsync($storeId, $call) {
        ksort($call);

        $basedCall = base64_encode(Mage::helper('core')->jsonEncode($call));
        $signature = trim($this->getApiHapiKey($storeId));
       
		
        $requestBody = array(
            's'   => $signature,
            'hs'  => $basedCall
        );
		if($this->isEnabled($storeId) and $signature != '')
		{
			$hubspotApi = Mage::helper('cubix_hubspot/hubspot');
			$all_customers = $hubspotApi->getAllContactsArray($signature);
			$customer_data = array();
			if(!empty($call)){				
				if(isset($call['events']) and !empty($call['events'])){
					$i=0;
					foreach($call['events'] as $call_data)
					{
						$email_res = $this->get_email($all_customers,$call_data['params']['email']);
						if(!$email_res){
							$customer_res = $hubspotApi->create_contact($signature, $call_data['params']);
						}
					}
				}
			}
		}
		return $signature;
	}
	
	//check email already exist at hubspot
	private function get_email($sector_array, $customer_email) 
	{
		foreach ($sector_array as $sec) 
			if (in_array($customer_email, $sec))
				return array('email'=>$sec['email'],'vid'=>$sec['hubspot_id'],'portal_id'=>$sec['portal_id']);
	
		return false;
	}
	
	//check email already exist at hubspot
	private function getDealName($sector_array, $deal_name) 
	{
		foreach ($sector_array as $sec) 
			if (in_array($deal_name, $sec))
				return array('deal_name'=>$sec['deal_name'],'deal_id'=>$sec['hubspot_deal_id'],'vid'=>$sec['client_hubspot_user_id']);
	
		return false;
	}
	/**
     * Create submition ready arrays from Array of Mage_Sales_Model_Customer
     * @param Array(Mage_Sales_Model_Order) $customers
     * @return Array of Arrays
     */
    private function _buildCustomersForSubmition($customers) {
        $customersForSubmition = array();
        foreach ($customers as $customer) {
			$customer_data = $customer->getData();
            if (isset($customer_data['entity_id'])) {
				$n_customer = Mage::getModel('customer/customer')->load($customer_data['entity_id']);
                array_push($customersForSubmition, $this->_buildCustomerForSubmition($n_customer));
            }
        }
        return $customersForSubmition;
    }
	
	private function _buildCustomersCall($storeId, $customersForSubmition) {
        return array(
            'token'    => $this->getApiHapiKey($storeId),
            'events'   => $customersForSubmition,
            // for debugging/support purposes
            'platform' => 'Magento ' . Mage::getEdition() . ' ' . Mage::getVersion(),
            'version'  => (string)Mage::getConfig()->getModuleConfig("Cubix_Hubspot")->version
        );
    }
	
	private function _buildCustomerForSubmition($customer) {
        $customerDetails = $this->prepareCustomerDetails($customer);
        // initialize additional params
        $callParameters = false;
        // check if order has customer IP in it
        $ip = ''; 
        if ($ip) {
            $callParameters = array('use_ip' => $ip);
        }
        // initialize time
        $time = false;
        $identityData = false; 

        return $this->_buildEventArray(
            $customerDetails['email'], 'customer', $customerDetails, $identityData, $time, $callParameters
        );
    }
	
	/**
     * Get order details and sort them for Hubspot
     *
     * @param  Mage_Sales_Model_Order $order
     * @return array
     */
    public function prepareCustomerDetails($customer)
    {    
        $customerAddress = array();
		$customer_data = $customer->getData();
		foreach ($customer->getAddresses() as $address)
		{
		   $customerAddress = $address->toArray();
		}
		$all_data = $customer_data + $customerAddress;
		$data['email'] 		= $all_data['email'];
		$data['firstname'] 	= isset($all_data['firstname']) ? $all_data['firstname'] : "";
		$data['lastname'] 	= isset($all_data['lastname']) ? $all_data['lastname'] : "";
		$data['city'] 		= isset($all_data['city']) ? $all_data['city'] : "";
		$data['country'] = isset($all_data['country_id']) ? $all_data['country_id'] : "";
		$data['state'] 	= isset($all_data['region']) ? $all_data['region'] : "";
		$data['zip'] 	= isset($all_data['postcode']) ? $all_data['postcode'] : "";
		$data['phone'] 	= isset($all_data['telephone']) ? $all_data['telephone'] : "";
		$data['address'] 	= isset($all_data['street']) ? $all_data['street'] : "";
        return $data;
    }
	
	################ import customers batch api end ############
	
	################ import custom_fields batch api start ############
	
	/**
     * Create HTTP request to Hubspot API to sync multiple customers
     *
     * @param Array(Mage_Sales_Model_Customer) $customers
     * @return void
     */
    public function callCustomFieldsBatchApi($storeId)
    {        
		try {
            $call = $this->CreateCustomField($storeId); 
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'Cubix_Hubspot_CustomFields.log');
        }
    }
	
	//
	public function CreateCustomField($storeId)
	{
		$signature = trim($this->getApiHapiKey($storeId));
		if($this->isEnabled($storeId) and $signature != '')
		{
			$hubspotApi = Mage::helper('cubix_hubspot/hubspot');
			$custom_fields_list = $this->CustomFieldsMerge();	
			$hubspot_deal_properties = $hubspotApi->get_all_DealProperties($signature);
			foreach($custom_fields_list as $custom_field)
			{				
				if(!$res = $this->verifyHubspotCustomFieldExist($hubspot_deal_properties, $custom_field['name']))
				{
					$field_res = $hubspotApi->create_deal_property($signature, $custom_field);
				}
			}			
		}else{ 
			return false; 
		}
		return false;
	}
	
	//check custom_field already exist at hubspot
	private function verifyHubspotCustomFieldExist($sector_array, $custom_field) 
	{
		foreach ($sector_array as $sec) 
			if (in_array($custom_field, $sec))
				return $sec['field_name'];
	
		return false;
	}
	
	//merge custom_fields and custom_item_fields
	private function CustomFieldsMerge()
	{
		$custom_fields1 = $this->CustomFieldsList();
		$custom_fields2 = $this->CustomItemFieldsList();
		$custom_fields_merge = $custom_fields1 + $custom_fields2;
		return $custom_fields_merge;
	}
	## custom item fields
	private function CustomItemFieldsList()
	{
		$custom_item_fields = array( 
								array('name' 		=> '_id'),
								array('name' 		=> '_sku'),
								array('name' 		=> '_name'),
								array('name' 		=> '_option_name'),
								array('name' 		=> '_price'),
								array('name' 		=> '_option_price'),
								array('name' 		=> '_url'),
								array('name' 		=> '_quantity'),
							);
		$all_custom_item_fields = array();
		$j2 = 113;
		for($i3=1; $i3 <= 10; $i3++)
		{
			$j = $j2;
			foreach($custom_item_fields as $custom_item)
			{					
				$item_array = array();
				$item_array['name'] = 'item_'.$i3.$custom_item['name']; 
				$item_array['label'] = 'item_'.$i3.$custom_item['name'];
				$item_array['description'] = 'Magento item_'.$i3.$custom_item['name']; 
				$item_array['displayOrder'] = $j;
				
				$all_custom_item_fields[$j] = $item_array; 
				$j++;
			}
			$j2 = $j;
		}
	  return $all_custom_item_fields;	
	}
	###CUSTOM FIELDS
	private function CustomFieldsList()
	{
		$custom_fields = array( 
							array(
								 'name' 		=> 'order_id',
								 'label' 		=> 'Order Id',
								 'description' 	=> 'Magento Order Id',
								 'displayOrder' => 100
								),
								array(
								 'name' 		=> 'order_status',
								 'label' 		=> 'Order Status',
								 'description' 	=> 'Magento Order Status',
								 'displayOrder' => 101
								),
								array(
								 'name' 		=> 'shipping_amount',
								 'label' 		=> 'Shipping Amount',
								 'description' 	=> 'Magento Order Shipping Amount',
								 'displayOrder' => 102
								),
								array(
								 'name' 		=> 'tax_amount',
								 'label' 		=> 'Tax Amount',
								 'description' 	=> 'Magento Order Tax Amount',
								 'displayOrder' => 103
								),
								array(
								 'name' 		=> 'billing_phone',
								 'label' 		=> 'Billing Phone',
								 'description' 	=> 'Magento Billing Phone',
								 'displayOrder' => 104
								),
								array(
								 'name' 		=> 'billing_country',
								 'label' 		=> 'Billing Country',
								 'description' 	=> 'Magento Billing Country',
								 'displayOrder' => 105
								),
								array(
								 'name' 		=> 'billing_region',
								 'label' 		=> 'Billing Region',
								 'description' 	=> 'Magento Billing Region',
								 'displayOrder' => 106
								),
								array(
								 'name' 		=> 'billing_city',
								 'label' 		=> 'Billing City',
								 'description' 	=> 'Magento Billing City',
								 'displayOrder' => 107
								),
								array(
								 'name' 		=> 'billing_postcode',
								 'label' 		=> 'Billing Postcode',
								 'description' 	=> 'Magento Billing Postcode',
								 'displayOrder' => 108
								),
								array(
								 'name' 		=> 'billing_address',
								 'label' 		=> 'Billing Address',
								 'description' 	=> 'Magento Billing Address',
								 'displayOrder' => 109
								),
								array(
								 'name' 		=> 'use_ip',
								 'label' 		=> 'use_ip',
								 'description' 	=> 'Magento use_ip',
								 'displayOrder' => 110
								),
								array(
								 'name' 		=> 'order_server_time',
								 'label' 		=> 'order_server_time',
								 'description' 	=> 'Magento order_server_time',
								 'displayOrder' => 111
								),
								array(
								 'name' 		=> 'order_time',
								 'label' 		=> 'order_time',
								 'description' 	=> 'Magento order_time',
								 'displayOrder' => 112
								),
							);
		return $custom_fields;					
	}
	
	//delete deal's custom properties
	public function deleteDealCustomProperties($storeId){
		
		$custom_fields = $this->CustomFieldsMerge();
		$signature = trim($this->getApiHapiKey($storeId));
		$resp = '';
		if($this->isEnabled($storeId) and $signature != '')
		{
			$hubspotApi = Mage::helper('cubix_hubspot/hubspot');
			foreach($custom_fields as $custom_field){
				$resp = $hubspotApi->deleteDealCustomProperty($signature,$custom_field['name']);
			}
		}
		return $resp;		
	}
}