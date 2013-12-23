<?php
/**
 *	Upsell Block class for Similarity module
 *	Replacing upsell data with PredictionIO data
 *	
 * @category    Richdynamix
 * @package     Richdynamix_SimilarProducts
 * @author 		Steven Richardson (steven@richdynamix.com) @troongizmo
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Richdynamix_SimilarProducts_Block_Catalog_Product_List_Upsell extends Mage_Catalog_Block_Product_List_Upsell
{
	/**
	 * Rewrite of parent::_prepareData() if 
	 * module enabled and has data relating to current product and
	 * customer is logged in.
	 * @return mixed _itemCollection or parent::_prepareData()
	 */
	protected function _prepareData()
    {
    	$_helper = Mage::helper('similarproducts');
    	$product = Mage::registry('product');

    	if ($_helper->isEnabled() && Mage::getSingleton('customer/session')->isLoggedIn()) {
	    	if ($similarproducts = $_helper->getSimilarProducts($product)) {
	    	
	            $collection = Mage::getResourceModel('catalog/product_collection');
	            Mage::getModel('catalog/layer')->prepareProductCollection($collection);
	            $collection->addAttributeToFilter('entity_id', array('in' => $similarproducts));

	            $this->_itemCollection = $collection;

	            return $this->_itemCollection;

	    	} else {
	    		return parent::_prepareData();
	    	}	
    	} else {
    		return parent::_prepareData();
    	}       
    }
}