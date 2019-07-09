<?php
/**
 * Helper class for HubSpot properties
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Helper_Hubspot extends Mage_Core_Helper_Abstract{
	public $db;
	public $session;
	public $messages = array('err'=>array(),'sec'=>array(),'alr'=>array());							
	public $auth;
	public $base_url;
	public $atts = array();
	public $j_data=array();
	public $hapikey = "";
	private $apiURL = "http://api.hubapi.com/";
	
	public function __construct(){			
			$this->base_url = ''; //url('/')."/";
	}

	public	function create_contact($api_key, $data = NULL){
			$this->hapikey = $api_key;
			
			$arr = array(
				'properties' => array(
					array(
					'property' => 'email',
					'value' => $data['email']
					),
					array(
					'property' => 'firstname',
					'value' => $data['firstname']
					),
					array(
					'property' => 'lastname',
					'value' => $data['lastname']
					),
					array(
					'property' => 'phone',
					'value' => $data['phone']
					),
					array(
					'property' => 'country',
					'value' => $data['country']
					),
					array(
					'property' => 'state',
					'value' => $data['state']
					),
					array(
					'property' => 'city',
					'value' => $data['city']
					),
					array(
					'property' => 'zip',
					'value' => $data['zip']
					),
					array(
					'property' => 'address',
					'value' => $data['address']
					)
				)
			);

			$endpoint = $this->apiURL.'contacts/v1/contact?hapikey=' . $this->hapikey;
			return $this->hubspot_curl($arr,$endpoint);
		}

		
	//find contact with email
	public function find_ContactByEmail($api_key, $email){
 			$this->hapikey = $api_key;
			$endpoint = $this->apiURL.'/contacts/v1/contact/email/'.$email.'/profile?hapikey='.$this->hapikey;
			return $this->hubspot_curl_get($endpoint);
	}

		
	//create company 
	public 	function company_create($api_key, $data = NULL)
		{
			$this->hapikey = $api_key;
			$arr = array(
				'properties' => array(
					array(
					'name' => 'name',
					'value' => 'Mytest Company'
					),
					array(
					'name' => 'description',
					'value' => 'My test company description'
					)
				)
			);

			$endpoint = $this->apiURL.'companies/v2/companies?hapikey=' .$this->hapikey;
			return $this->hubspot_curl($arr,$endpoint);
		}
		
      //get data using using curl POST method
	 public 	function hubspot_curl($arr,$endpoint)
		{
			$post_json = json_encode($arr);
			 
			$ch = @curl_init();
			@curl_setopt($ch, CURLOPT_POST, true);
			@curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
			@curl_setopt($ch, CURLOPT_URL, $endpoint);
			@curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = @curl_exec($ch);
			$status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_errors = curl_error($ch);
			@curl_close($ch);

			$data = array('errors'=> $curl_errors,'status_code'=>$status_code,'response'=>json_decode($response));	
			return $data;	
		}

		
		//get data using using curl GET method
		public function hubspot_curl_get($endpoint){

			$ch = curl_init();
			$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			);
			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
			//curl_setopt($ch, CURLOPT_POSTFIELDS,$body);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_errors = curl_error($ch);
			curl_close($ch);
			$data = array('errors'=> $curl_errors,'status_code'=>$status_code,'response'=>json_decode($response));	

			return $data;
		}
		
		//get data using using curl DELETE method
		public function hubspot_curl_delete($endpoint){

			$ch = curl_init();
			$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			);

			curl_setopt($ch, CURLOPT_URL, $endpoint);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); 
			//curl_setopt($ch, CURLOPT_POSTFIELDS,$body);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curl_errors = curl_error($ch);
			curl_close($ch);
			$data = array('errors'=> $curl_errors,'status_code'=>$status_code,'response'=>json_decode($response));	
			return $data;
		}
		
	   //hubspot get owners list for assign deal
       public  function deleteDealCustomProperty($apikey,$dealproperty){
		    $this->hapikey = $apikey;
			$endpoint = $this->apiURL.'properties/v1/deals/properties/named/'.$dealproperty.'?hapikey='.$this->hapikey;
			$data = $this->hubspot_curl_delete($endpoint);
	   }
 
	   //hubspot get owners list for assign deal
       public  function get_Allowners($apikey){
		    $this->hapikey = $apikey;
			$endpoint = $this->apiURL.'owners/v2/owners?hapikey='.$this->hapikey;
			$data = $this->hubspot_curl_get($endpoint);
			$owners_list = array();
			$j = 0;
			if($data['status_code'] == 200){
				foreach($data['response'] as $owner){
					//debug_array($owner);
					$owners_list[$j]['portalId'] = $owner->portalId;
					$owners_list[$j]['ownerId'] = $owner->ownerId;
					$owners_list[$j]['email'] = $owner->email;
					$owners_list[$j]['first_name'] = $owner->firstName;
					$owners_list[$j]['last_name'] = $owner->lastName; //createdAt
					$createdAt = $owner->createdAt/1000;
					$owners_list[$j]['_cdate'] = gmdate('Y-m-d H:i:s',$createdAt);
					$j++;
				}
			}
			return $data;
		}
		
	//get hubspot owner id by email
	public 	function get_ownerByEmail($api_key, $email){
			$this->hapikey = $api_key;
			$owners = $this->get_Allowners();
			$email_owner = array();
			//check email if exist
			foreach($owners as $owner){
				if($email == $owner['email']){
					$email_owner['ownerId'] = $owner['ownerId'];
					$email_owner['portalId'] = $owner['portalId'];
					$email_owner['email'] = $owner['email'];
				}
			}
			//either assign default owner
			if(empty($email_owner)){
				$email_owner['ownerId'] = $owners[0]['ownerId'];
				$email_owner['portalId'] = $owners[0]['portalId'];
				$email_owner['email'] = $owners[0]['email'];
			}
			return $email_owner;
		}
		
		
		//create deal property
		public function create_deal_property($api_key, $data){
			$this->hapikey = $api_key;
			$arr = array(
								'name' => $data['name'],
								'label' => $data['label'],
								'description' => $data['description'],
								'groupName' => 'dealinformation',
								'type' => 'string',
								'fieldType' => 'text',
								'hidden' => false,
								'displayOrder' => $data['displayOrder']
							);
			$endpoint = $this->apiURL.'properties/v1/deals/properties/?hapikey='.$this->hapikey;	
			return $this->hubspot_curl($arr,$endpoint);				
		}
		//Get deal property
		public function get_deal_property($api_key, $data){
			$this->hapikey = $api_key;
			$property_name = $data;
			$endpoint = $this->apiURL.'properties/v1/deals/properties/named/'.$property_name.'?hapikey='.$this->hapikey;	
			return $this->hubspot_curl_get($endpoint);				
		}
		//create deal
		public function create_deal($api_key, $data=NULL,$property2)
		{
			$this->hapikey = $api_key;
			$property1 = array(
							array(
								"value" => $data['dealname'],
								"name" => "dealname"
							),
							array(
								"value"=> "appointmentscheduled",
                    			"name"=> "dealstage"
							),
							array(
								"value" => "default",
								"name"=> "pipeline"
							),
							array(
								"value" => "newbusiness",
								"name"=> "dealtype"
							),							
							array(
								'value' => time(),
								'name'	=> 'createdate'
							)
						);
			$property3 = $property1+$property2;			
			$arr = array(
						'associations'=>array('associatedVids' => array($data['sender_vid'])),
						'portalId'=>$data['portalId'],
						'properties'=> $property3
					);
			Mage::log($arr, null, 'hubspot_deal_arr_log.log', false);
			$endpoint = $this->apiURL.'deals/v1/deal?hapikey='.$this->hapikey;
			return $this->hubspot_curl($arr,$endpoint);	
		}
		
	public function get_all_contact($api_key,$vidOffset=1){	
			$this->hapikey = $api_key;
			$endpoint = $this->apiURL.'/contacts/v1/lists/all/contacts/all?hapikey=' . $this->hapikey.'&count=100&vidOffset='.$vidOffset;
			$allcontact = $this->hubspot_curl_get($endpoint);
			$contact_array = array();
			if($allcontact){
				if($allcontact['status_code']==200){
					foreach($allcontact['response'] as $data){
					if(is_array($data)){
						foreach($data as $contact){		
							 			 
							$hubspot_user_id =  $contact->{'vid'};
							$firstname = $contact->properties->firstname->value;
							$lastname = '';
							$phone = ''; 
							if(isset($contact->properties->lastname)){
								$lastname = $contact->properties->lastname->value;
							}
							if(isset($contact->properties->phone)){
								$phone = $contact->properties->phone->value;
							}
							$email = ''; 
							foreach($contact->{'identity-profiles'}[0]->{'identities'} as $email_find){
								if(strtolower($email_find->type) == 'email'){ $email = $email_find->value; }
							}
							$date =  $contact->{'identity-profiles'}[0]->{'identities'}[0]->timestamp;
							$c_date = $date/1000;	
							$hubspot_date = date('Y-m-d H:i:s', $c_date);
							$contact_array[] = array (
								'hubspot_id'=> $hubspot_user_id,
								'name'=> trim($firstname." ".$lastname),
								'email'=> $email,
								'hubspot_date'=> $hubspot_date,
								'phone_no'=> $phone
							); 
						}
					}
				}
				}
		}
		return $contact_array;
	}
	
	public function getAllContactsByPage($api_key,$i2,$res_array=array(),$i=1)
	{
		$this->hapikey = $api_key;
		$endpoint = $this->apiURL.'/contacts/v1/lists/all/contacts/all?hapikey=' . $this->hapikey.'&count=100&vidOffset='.$i2;		
		$res = $this->hubspot_curl_get($endpoint);
		if($res['status_code'] == 200){				
			$res_array['contacts'][$i] = $res['response']->contacts;				
			if($res['response']->{'has-more'}){ 
				$i++;
				return $this->getAllContactsByPage($api_key,$res['response']->{'vid-offset'},$res_array,$i); 
			}else{
				$res_array['contacts'][$i] = $res['response']->contacts;
				return $res_array;	
			}
		}else{  return false; }
		
	}
	
	public function getAllContactsArray($api_key){
		$data2 = array();
		$i = 1;
		$j = 0;
		$res = $this->getAllContactsByPage($api_key,$i);
		if(!empty($res))
		{
			foreach($res['contacts'] as $data){
				if(is_array($data)){
					foreach($data as $contact){		 
						$hubspot_user_id =  $contact->{'vid'};
						$firstname = $contact->properties->firstname->value;
						$lastname = '';
						$phone = ''; 
						if(isset($contact->properties->lastname)){
							$lastname = $contact->properties->lastname->value;
						}
						if(isset($contact->properties->phone)){
							$phone = $contact->properties->phone->value;
						}
						$email = ''; 
						foreach($contact->{'identity-profiles'}[0]->{'identities'} as $email_find){
							if(strtolower($email_find->type) == 'email'){ $email = $email_find->value; }
						}
						$date =  $contact->{'identity-profiles'}[0]->{'identities'}[0]->timestamp;
						$c_date = $date/1000;	
						$hubspot_date = date('Y-m-d H:i:s', $c_date);
						$data2[$j] = array (
							'hubspot_id'=> $hubspot_user_id,
							'name'=> trim($firstname." ".$lastname),
							'email'=> $email,
							'hubspot_date'=> $hubspot_date,
							'phone_no'=> $phone,
							'portal_id' => $contact->{'portal-id'}
						); 
						$j++;
					}
				}
			}
		}
		return $data2;
	}
	
	//get all deal properties
	public function get_all_DealProperties($api_key)
	{	
		$this->hapikey = $api_key;
		$endpoint = $this->apiURL.'/properties/v1/deals/properties?hapikey=' . $this->hapikey;
		$res = $this->hubspot_curl_get($endpoint);
		$items_array = array();			
		if(isset($res['status_code']) and $res['status_code']==200){
			foreach($res['response'] as $data){
				$items_array[]['field_name'] = $data->name;
			}
		}
		return $items_array;	
	}
				
	//get all deals
	public function getAllDeals($api_key){
		$this->hapikey = $api_key;
		$endpoint = $this->apiURL.'/deals/v1/deal/paged?hapikey=' . $this->hapikey;
		$all_deals = array();
		$alldeals = $this->hubspot_curl_get($endpoint);	
		foreach($alldeals['response'] as  $deals){
			if(is_array($deals)){
				foreach($deals as $deal){
					if(isset($deal->dealId)){
						$all_deals[] = $this->getDealDetail($api_key,$deal->dealId);
					}
				}
			}
		}
		return $all_deals;
	}
	
	// get deal details by deal_id
	public function getDealDetail($api_key, $deal_id){
		$this->hapikey = $api_key;
		$endpoint = $this->apiURL.'/deals/v1/deal/'.$deal_id.'?hapikey='.$this->hapikey;
		$deal = $this->hubspot_curl_get($endpoint);
		$deal = ($deal['response']);
		$data = array();
		if($deal and isset($deal->dealId)){
			$deal_id = $deal->dealId;
			$message_subject = $deal->{'properties'}->dealname->value;
			$desccription = ''; if(isset($deal->{'properties'}->description->value)){ $desccription = $deal->{'properties'}->description->value; }
			if(isset($deal->{'properties'}->hubspot_owner_id->value)){
				$hubspot_owner_id =  $deal->{'properties'}->hubspot_owner_id->value;
			}else{ $hubspot_owner_id = 0;}
			$client_hubspot_id = $deal->associations->associatedVids[0]; 			
			 
			$data = array(	
					'hubspot_deal_id' => $deal_id,
					'deal_name' =>$message_subject,				
					'description' => $desccription,					
					'subject' =>$message_subject,					
					'json_deal' => json_encode($deal),
					'hubspot_owner_id' => $hubspot_owner_id,
					'client_hubspot_user_id' => $client_hubspot_id
				);	
		}
		return 	$data;
	}
}

?>