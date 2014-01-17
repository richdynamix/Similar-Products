<?php
/**
 *	Observer class for Similarity module
 *	handling all event hooks
 *	
 * @category    Richdynamix
 * @package     Richdynamix_SimilarProducts
 * @author 		Steven Richardson (steven@richdynamix.com) @troongizmo
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Richdynamix_SimilarProducts_Model_Observer
{

	/**
	 * Define the helper object
	 * @var NULL
	 */
	protected $_helper;

	/**
	 * Construct for assigning helper class to helper object
	 */
	public function __construct() {
		$this->_helper = Mage::helper('similarproducts');
	}

	/**
	 * When the customer is not logged in we should still capture
	 * data in case they login at basket after viewing several 
	 * products. We log items to the session then extract later
	 * when they login.
	 * 
	 * @param  string $action  Define the action to watch
	 * @param  Mage_Catalog_Model_Product $product [description]
	 */
	public function logGuestActions($action, Mage_Catalog_Model_Product $product, $rating = null)
	{
		
		$guestActions = Mage::getSingleton('core/session')->getGuestActions();
		
		if (isset($guestActions) && $guestActions != NULL) {
			if ($action === 'view') {
				array_push($guestActions['product_view'], $product->getId());
			}
			if ($action === 'rate') {
				$guestActions['product_rate'][$product->getId()] = $rating;
			}
			Mage::getSingleton('core/session')->setGuestActions($guestActions);
		} else {
			switch ($action) {
				case 'view':
					$guestActions['product_view'][] = $product->getId();
					break;
				case 'rate':
					$guestActions['product_rate'][$product->getId()] = $rating;
					break;
			}
			Mage::getSingleton('core/session')->setGuestActions($guestActions);		
		}

		// Mage::getSingleton('core/session')->unsGuestActions();
		// var_dump($guestActions);

	}

	/**
	 * Event to fire when the customer logs in
	 * @param Varien_Event_Observer $observer customer_login
	 */
	public function addCustomer(Varien_Event_Observer $observer) 
	{
		if ($this->_helper->isEnabled() && Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customer = $observer->getEvent()->getCustomer();	
			$this->_helper->_addCustomer($customer->getId());

			// Check if there is a guest actions log
			$guestActions = Mage::getSingleton('core/session')->getGuestActions();
			if (isset($guestActions)) {
				// there is actions been logged prior to login, lets process them
				$this->processGuestActions($guestActions, $customer->getId());
			}

		}
	}

	/**
	 * Method used to do the guest action logging.
	 * @param string $guestActions type of action being defined
	 * @param int $customerId Customer ID of logged in customer
	 */
	protected function processGuestActions($guestActions, $customerId)
	{
		if (isset($guestActions['product_view'])) {
			foreach ($guestActions['product_view'] as $item) {
				$product = Mage::getModel('catalog/product')->load($item);
				$this->_helper->_addItem($product);
				$this->_helper->_addAction($product->getId(), $customerId, 'view');
			}
		}
		if (isset($guestActions['product_rate'])) {
			foreach ($guestActions['product_rate'] as $product_id => $rating) {
				$product = Mage::getModel('catalog/product')->load($product_id);
				$this->_helper->_addItem($produproductct_id);
				$this->_helper->_addAction($product_id, $customerId, 'rate', $rating);
			}
		}
		Mage::getSingleton('core/session')->unsGuestActions();
	}

	/**
	 * Event to fire when the customer views a product
	 * @param  Varien_Event_Observer $observer [description]
	 */
	public function productView(Varien_Event_Observer $observer)
	{

		if ($this->_helper->isEnabled() && Mage::getSingleton('customer/session')->isLoggedIn()) {
			$product = Mage::registry('current_product');
			$customer = Mage::getSingleton('customer/session')->getCustomer();
			$this->_helper->_addItem($product);
			$this->_helper->_addAction($product->getId(), $customer->getId(), 'view');
		} else {
			$this->logGuestActions('view', Mage::registry('current_product'));
		}
	}	

	/**
	 * Event to fire when the customer reviews a product
	 * @param  Varien_Event_Observer $observer [description]
	 */
	public function productRate(Varien_Event_Observer $observer)
	{
		if ($this->_helper->isEnabled()) {
			$customer = Mage::getSingleton('customer/session')->getCustomer();			
			$product = Mage::registry('current_product');

			$object = $observer->getEvent()->getObject();
        	$data = $object->getData();

        	$newSumRatings = 0;
	        foreach($data['ratings'] as $r) {
	            $value = $r % 5;
	            $newSumRatings += ($value) ? $value : 5;
	        }
	        $rating = $newSumRatings/count($data['ratings']);

		    if (Mage::getSingleton('customer/session')->isLoggedIn()) {
		    	$this->_helper->_addItem($product);
				$this->_helper->_addAction($product->getId(), $customer->getId(), 'rate', $rating);
			} else {
				$this->logGuestActions('rate', Mage::registry('current_product'), $rating);
			}
		}
	}

	/**
	 * Event to fire when the customer buys a product
	 * @param  Varien_Event_Observer $observer [description]
	 */
	public function productSale(Varien_Event_Observer $observer)
	{
		if ($this->_helper->isEnabled() && Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customer = Mage::getSingleton('customer/session')->getCustomer();			
			
			$order = $observer->getEvent()->getOrder();
     		$items = $order->getItemsCollection();
			
			foreach ($items as $item) {
				$this->_helper->_addItems($item->getProductId());
				$this->_helper->_addAction($item->getProductId(), $customer->getId(), 'conversion');
			}     		
		}
	}
		
}
