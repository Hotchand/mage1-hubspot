<?php
/**
 * Observer class for import orders to HubSpot
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Model_Observer
{

			//Import Order to HubSpot on successful checkout action
			public function OrderCreate(Varien_Event_Observer $observer)
			{
				
				$helper 	= Mage::helper('cubix_hubspot');
				$storeId 	= Mage::app()->getStore()->getStoreId();				
				$signature 	= trim($helper->getApiHapiKey($storeId));
				
				$ob_order	= array();
				
				if($helper->isEnabled($storeId) and $signature != '')
				{
					$orderIds 	= $observer->getEvent()->getOrderIds();					
					
					$hubspotApi_helper = Mage::helper('cubix_hubspot/hubspot');
					$helper->CreateCustomField($storeId);
					
					if (empty($orderIds) || !is_array($orderIds)) {
						return;
					}
					$collection = Mage::getResourceModel('sales/order_collection')
							->addFieldToFilter('entity_id', array('in' => $orderIds));
					$orders = array();
					foreach ($collection as $order) {
						$orders[] = $order;
					}
					
					$i=0;
					foreach ($orders as $order) {						
						$order_detials = $helper->prepareOrderDetails($order);
						$entity = $order->getData();
						
						$billing_address = $order->getBillingAddress();
						$billAddr = $billing_address->getData();
						$ob_order[$i]['storeId'] 		= $storeId;	
						$ob_order[$i]['hapikey'] 		= $signature;						
						$ob_order[$i]['order_details'] 	= $order_detials;
						$ob_order[$i]['order_billing']	= $billAddr;	
						$ob_order[$i]['use_ip'] = $order->getRemoteIp();
						$i++;					
					}
						if(!empty($ob_order)){
							foreach($ob_order as $order_data)
							{
								$customer_data = array();
								$orders_data = array();
								$deal_info = array();
								###customer data for create/post
								$customer_vid = 0;
								$portal_id = 0; 
								$customer_data['firstname'] 	= $order_data['order_billing']['firstname'];
								$customer_data['lastname'] 		= $order_data['order_billing']['firstname'];
								$customer_data['email'] 		= $order_data['order_billing']['email'];
								$customer_data['phone'] 		= $order_data['order_billing']['telephone'];
								$customer_data['country'] 		= $order_data['order_billing']['country_id'];
								$customer_data['state'] 		= $order_data['order_billing']['region'];
								$customer_data['city'] 			= $order_data['order_billing']['city'];
								$customer_data['zip'] 			= $order_data['order_billing']['postcode'];
								$customer_data['address'] 		= $order_data['order_billing']['street'];
								
								### find/create contact and get portal id and user id
								if ($email_exists = $hubspotApi_helper->find_ContactByEmail($signature, $customer_data['email']) and $email_exists['status_code'] == 200){
									$customer_vid = $email_exists['response']->vid;
									$portal_id =$email_exists['response']->{'portal-id'};
								}else{
									$customer_res = $hubspotApi_helper->create_contact($signature, $customer_data);
									if($customer_res['status_code'] == 200){
										$customer_vid = $customer_res['response']->vid;
										$portal_id = $customer_res['response']->{'portal-id'};
									}
								}
								
								###order data
								$deal_info['dealname']				= $order_data['order_details']['order_id'].' - '.$customer_data['firstname'].' '.$customer_data['lastname'];
								$deal_info['portalId']				= $portal_id;
								$deal_info['sender_vid']			= $customer_vid;
								
								$i3 = 5;
								$orders_data = array();
								$orders_data[$i3]['name'] 			= 'order_id';			
								$orders_data[$i3]['value']			= $order_data['order_details']['order_id'];
								$i3++;
								$orders_data[$i3]['name']= 'order_status';
								$orders_data[$i3]['value']		= $order_data['order_details']['order_status'];
								$i3++;
								$orders_data[$i3]['name']				= 'amount';
								$orders_data[$i3]['value']				= $order_data['order_details']['amount'];
								$i3++;
								$orders_data[$i3]['name']		= 'shipping_amount';
								$orders_data[$i3]['value']		= $order_data['order_details']['shipping_amount'];
								$i3++;
								$orders_data[$i3]['name']			= 'tax_amount';
								$orders_data[$i3]['value']			= $order_data['order_details']['tax_amount'];
								$i3++;
								$orders_data[$i3]['name'] 		= 'billing_phone';
								$orders_data[$i3]['value'] 		= $order_data['order_details']['billing_phone'];
								$i3++;
								$orders_data[$i3]['name'] 	= 'billing_country';
								$orders_data[$i3]['value'] 	= $order_data['order_details']['billing_country'];
								
								if(isset($call_data['params']['billing_region'])){
									$i3++;
									$orders_data[$i3]['name'] 	= 'billing_region';
									$orders_data[$i3]['value'] 	= $order_data['order_details']['billing_region'];
								}
								$i3++;
								$orders_data[$i3]['name'] 		= 'billing_city';
								$orders_data[$i3]['value'] 		= $order_data['order_details']['billing_city'];
								$i3++;
								$orders_data[$i3]['name'] 	= 'billing_postcode';
								$orders_data[$i3]['value'] 	= $order_data['order_details']['billing_postcode'];
								$i3++;
								$orders_data[$i3]['name'] 	= 'billing_address';
								$orders_data[$i3]['value'] 	= $order_data['order_details']['billing_address'];
								
								if(isset($call_data['use_ip'])){
									$i3++;
									$orders_data[$i3]['name'] 			= 'use_ip';
									$orders_data[$i3]['value'] 			= $order_data['use_ip'];
								}
								$i3++;
								$orders_data[$i3]['name'] 		= 'order_server_time';
								$orders_data[$i3]['value'] 		= time();
								$i3++;
								$orders_data[$i3]['name'] 			= 'order_time';
								$orders_data[$i3]['value']			= time();
								
								
								#### product(s) purchased details
								$i2 = 1;
								foreach($order_data['order_details']['items'] as $item2){
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
								
								$deal_res = $hubspotApi_helper->create_deal($signature, $deal_info,$orders_data);	
						}
					}
				}
				 return $this;
			}
		
			//import customer to hubspot on account creation
			public function CreateContactRecord(Varien_Event_Observer $observer)
			{				
				$helper 	= Mage::helper('cubix_hubspot');
				$storeId 	= Mage::app()->getStore()->getStoreId();				
				$signature 	= trim($helper->getApiHapiKey($storeId));
				
				$event = $observer->getEvent();  //Fetches the current event
        		$customerArr = $event->getCustomer()->getData();	
				if($helper->isEnabled($storeId) and $signature != '')
				{
					$hubspotApi_helper = Mage::helper('cubix_hubspot/hubspot');
					
					$customer_data['firstname'] 	= $customerArr['firstname'];
					$customer_data['lastname'] 		= $customerArr['lastname'];
					$customer_data['email'] 		= $customerArr['email'];
					$customer_data['phone'] 		= '';
					$customer_data['country'] 		= '';
					$customer_data['state'] 		= '';
					$customer_data['city'] 			= '';
					$customer_data['zip'] 			= '';
					$customer_data['address'] 		= '';
					
					### find/create contact and get portal id and user id
					if ($email_exists = $hubspotApi_helper->find_ContactByEmail($signature, $customer_data['email']) and $email_exists['status_code'] == 200){
						$customer_vid = $email_exists['response']->vid;
						$portal_id =$email_exists['response']->{'portal-id'};
					}else{
						$customer_res = $hubspotApi_helper->create_contact($signature, $customer_data);
						if($customer_res['status_code'] == 200){
							$customer_vid = $customer_res['response']->vid;
							$portal_id = $customer_res['response']->{'portal-id'};
						}
					}											
				}
				return $this;
			} //end CreateContactRecord()	
				
} //end class
