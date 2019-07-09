<?php
/**
 * Button widget class
 * Add import model and render button view
 *
 * @author Hotchand Sajnani <hotchand.sukhram@cubixlabs.com>
 */
class Cubix_Hubspot_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cubix/system/config/button.phtml');
    }

    /**
     * Get import instance
     *
     * @return Cubix_Hubspot_Model_Import
     */
    public function getImport()
    {
        return Mage::getModel('cubix_hubspot/import');
    }

    /**
     * Get import instance
     *
     * @return boolean
     */
    public function showInStore()
    {
        return Mage::app()->getRequest()->getParam('store');
    }


    /**
     * Get import instance
     *
     * @return boolean
     */
    public function buttonEnabled()
    {
        $helper = Mage::helper('cubix_hubspot');

        $request = Mage::app()->getRequest();
        $storeId = $helper->getStoreId($request);

         return $helper->isEnabled($storeId) &&
            $helper->getApiHapiKey($storeId);
    }

    /**
    * Return element html
    *
    * @param  Varien_Data_Form_Element_Abstract $element
    * @return string
    */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
    * Return ajax url for button
    *
    * @return string
    */
    public function getAjaxUrl()
    {
        return Mage::helper('adminhtml')->getUrl("cubix_hubspot/adminhtml_ajax", array('isAjax'=> true));
    }

    /**
    * Generate button html
    *
    * @return string
    */
    public function getButtonHtml()
    {
        $button = $this->getLayout()
                       ->createBlock('adminhtml/widget_button')
                       ->setData(array(
                           'id'        => 'cubix_button',
                           'label'     => $this->helper('adminhtml')->__('Import orders'),
                           'onclick'   => 'javascript:import_cubix(); return false;'
                       ));

        return $button->toHtml();
    }
	
	/**
    * Generate custom fields button html
    *
    * @return string
    */
    public function getCustomFieldButtonHtml()
    {
        $button = $this->getLayout()
                       ->createBlock('adminhtml/widget_button')
                       ->setData(array(
                           'id'        => 'cubix_custom_field_button',
                           'label'     => $this->helper('adminhtml')->__('Import Custom Fields'),
                           'onclick'   => 'javascript:import_customfields_cubix(); return false;'
                       ));

        return $button->toHtml();
    }
	
	/**
    * Generate customers button html
    *
    * @return string
    */
    public function getCustomersButtonHtml()
    {
        $button = $this->getLayout()
                       ->createBlock('adminhtml/widget_button')
                       ->setData(array(
                           'id'        => 'cubix_customers_button',
                           'label'     => $this->helper('adminhtml')->__('Import Customers'),
                           'onclick'   => 'javascript:import_customers_cubix(); return false;'
                       ));

        return $button->toHtml();
    }
}
