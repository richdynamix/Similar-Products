<?php
/**
 *	Helper class for Similarity module
 *	handling most data transactions via cURL
 *	
 * @category    Richdynamix
 * @package     Richdynamix_SimilarProducts
 * @author 		Steven Richardson (steven@richdynamix.com) @troongizmo
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Richdynamix_SimilarProducts_Helper_Data extends Mage_Core_Helper_Abstract
{

	/**
	 * API Endpoint for users
	 * @var string
	 */
	protected $_userUrl = 'users.json';

	/**
	 * API Endpoint for items
	 * @var string
	 */
	protected $_itemsUrl = 'items.json';

	/**
	 * API Endpoint for users-to-item actions
	 * @var string
	 */
	protected $_actionsUrl = 'actions/u2i.json';

	/**
	 * API Endpoint for similarity engine.
	 * {engine} will be string replaced by the engine name
	 * you specify in the CMS configuration
	 * @var string
	 */
	protected $_engineUrl = 'engines/itemsim/{engine}/topn.json';

	/**
	 * Sets up cURL request paramaters for adding a customer
	 * @param int $customerId Customer ID of loggedin customer
	 */
	public function _addCustomer($customerId) {

		$fields_string = 'pio_appkey='.$this->getEngineKey().'&';
		$fields_string .= 'pio_uid='.$customerId;
		$this->sendData($this->getApiHost().':'.$this->getApiPort().'/'.$this->_userUrl, $fields_string);
	}

	/**
	 * Sets up cURL request paramaters for adding a product
	 * @param Mage_Catalog_Model_Product $product  Instance of Product Model
	 */
	public function _addItem(Mage_Catalog_Model_Product $product) {

		$cats = $this->getCategories($product);
		$fields_string = 'pio_appkey='.$this->getEngineKey().'&';
		$fields_string .= 'pio_iid='.$product->getId().'&';
		$fields_string .= 'pio_itypes='.$cats;
		$this->sendData($this->getApiHost().':'.$this->getApiPort().'/'.$this->_itemsUrl, $fields_string);
	}

	/**
	 * Sets up cURL request paramaters for adding a parent 
	 * item of ordered product (Since Upsells can only be shown on parents)
	 * @param int $productid  Product ID of purchased item
	 */
	public function _addItems($productid) {

		$product = Mage::getModel('catalog/product')->load($productid);
		$cats = $this->getCategories($product);
		if($product->getTypeId() == "simple"){
		    $parentIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId());
		    if(!$parentIds)
		        $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
		    if(isset($parentIds[0])){
		    	$_productId = $parentIds[0];
		    } else {
		    	$_productId = $product->getId();
		    }
		}

		$fields_string = 'pio_appkey='.$this->getEngineKey().'&';
		$fields_string .= 'pio_iid='.$_productId.'&';
		$fields_string .= 'pio_itypes='.$cats;
		$this->sendData($this->getApiHost().':'.$this->getApiPort().'/'.$this->_itemsUrl, $fields_string);

	}
	
	/**
	 * Sets up cURL request paramaters for adding a user-to-item action
	 * @param int $productid  Product ID of item to action
	 * @param int $customerId Customer ID of loggedin customer
	 */
	public function _addAction($productId, $customerId, $action, $rate = null) {

		$fields_string = 'pio_appkey='.$this->getEngineKey().'&';
		$fields_string .= 'pio_uid='.$customerId.'&';
		$fields_string .= 'pio_iid='.$productId.'&';
		if ($rate != null) {
			$fields_string .= 'pio_rate='.$rate.'&';	
		}
		$fields_string .= 'pio_action='.$action;
		$this->sendData($this->getApiHost().':'.$this->getApiPort().'/'.$this->_actionsUrl, $fields_string);
	}

	/**
	 * Gets comma seperated list of categories 
	 * belonging to product, used for pio_itypes in PredictionIO
	 * @param  Mage_Catalog_Model_Product $product Instance of Product Model
	 * @return string  Comma seperated categories
	 */
	public function getCategories(Mage_Catalog_Model_Product $product) {
		
		if ($product->getId()) {
			$categoryIds = $product->getCategoryIds();
			if (is_array($categoryIds) and count($categoryIds) >= 1) {
				$catsString = '';
				foreach ($categoryIds as $id) {
					$cat = Mage::getModel('catalog/category')->load($id);
					$catsString .= $cat->getName().',';
				}
				$cats = rtrim($catsString, ",");
				return $cats;
			}
			return '';
		}
	}

	/**
	 * Sets up cURL request paramaters for getting similar products
	 * from PredictionIO after data is trained
	 * @param  Mage_Catalog_Model_Product $product Instance of Product Model
	 * @return mixed (Array of product ID's or NULL when empty)
	 */
	public function getSimilarProducts (Mage_Catalog_Model_Product $product)
	{
		
		$engineUrl = str_replace('{engine}', $this->getEngineName(), $this->_engineUrl);

		$_currentProduct = 'pio_iid='.$product->getId();

		$_maxProductCount = 'pio_n='.$this->getProductCount();
		$_key = 'pio_appkey='.$this->getEngineKey();

		$cats = '';
		if ($this->isCategoryResults()) {
			$cats = '&pio_itypes='.$this->getCategories($product);
		}

		$url = $this->getApiHost() . ':' . $this->getApiPort() . '/' . $engineUrl; 
		$query = '?' . $_currentProduct. '&' . $_maxProductCount. '&' . $_key.$cats;

		$content = json_decode($this->getData($url, $query));

		if (isset($content->pio_iids)) {
			return $content->pio_iids;
		} else {
			return null;
		}
	}

	/**
	 * Perform the cURL GET Request
	 * @param  string $url   URL of PredictionIO API 
	 * @param  string $query Query params to get data
	 * @return string string version of JSON object to be parsed.
	 */
	public function getData($url, $query) {

		$ch = curl_init();

		// Set query data here with the URL
		curl_setopt($ch, CURLOPT_URL, $url . $query); 

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, '3');
		
		$content = trim(curl_exec($ch));

		curl_close($ch);

		return $content;
	}

	/**
	 * Perform the cURL POST Request
	 * @param  string $url   URL of PredictionIO API 
	 * @param  string $fields_string Query params for POST data
	 */
	public function sendData($url, $fields_string) {
		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_VERBOSE, 1);

		//execute post
		$result = curl_exec($ch);

		//close connection
		curl_close($ch);
	}

	/**
	 * Similarity Engine Key, Defined in PredictionIO
	 * @return string 
	 */
	public function getEngineKey(){
		return Mage::getStoreConfig('similarproducts/settings/predict_key');
	}

	/**
	 * Similarity Engine Name, Defined in PredictionIO
	 * @return string
	 */
	public function getEngineName(){
		return Mage::getStoreConfig('similarproducts/settings/engine_name');
	}

	/**
	 * PredictionIO URL
	 * @return string
	 */
	public function getApiHost(){
		return Mage::getStoreConfig('similarproducts/settings/predict_host');
	}

	/**
	 * PredictionIO API Port, Default is 8000 but needs to be defined
	 * @return string
	 */
	public function getApiPort(){
		return Mage::getStoreConfig('similarproducts/settings/predict_port');
	}

	/**
	 * Module ON/OFF Switch
	 * @return bool
	 */
	public function isEnabled(){
		return Mage::getStoreConfig('similarproducts/settings/enabled');
	}

	/**
	 * Determine if the results should be based on similar categories
	 * @return bool
	 */
	public function isCategoryResults(){
		return Mage::getStoreConfig('similarproducts/settings/category_results');
	}

	/**
	 * Get maximum returned products
	 * @return int
	 */
	public function getProductCount(){
		return Mage::getStoreConfig('similarproducts/settings/product_count');
	}

}
	 