<?php
/**
* Simple script to import customers, products and actions 
* into prediction engine for all past orders.
* 
* This will only add a coversion action type as we cannot determine 
* the previous actions of the customers
*
* @category    Richdynamix
* @package     Richdynamix_SimilarProducts
* @author      Steven Richardson (steven@richdynamix.com) @troongizmo
* @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/
require_once 'abstract.php';
class Richdynamix_Shell_Similarity extends Mage_Shell_Abstract
{

    /**
     * Define the a list of stores to run
     * @var array
     */
    protected $_stores = array();

    /**
     * Store count for reporting
     * @var int
     */
    protected $_sCount = 0;
    
    /**
     * Define the helper object
     * @var NULL
     */
    protected $_helper;

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
     * Setup the run command with the right data to process
     */
    public function __construct() {
        parent::__construct();
        
        set_time_limit(0);

        $this->_helper = Mage::helper('similarproducts');


        if($this->getArg('stores')) {
            $this->_stores = array_merge(
                $this->_stores,
                array_map(
                    'trim',
                    explode(',', $this->getArg('stores'))
                )
            );
        }

    }

    // Shell script point of entry
    public function run() {
        
        try {

            if(!empty($this->_stores)) {
                $selectedStores = '"'.implode('", "', $this->_stores).'"';
            } else {
                $selectedStores = 'All';
            }

            printf(
                'Selected stores: %s'."\n",
                $selectedStores
            );

            echo "\n";

            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                $storeName = $store->getName();
                if(!empty($this->_stores) && !in_array($storeName, $this->_stores)) {
                    continue;
                }
                $this->_processStore($store);
            }

            printf(
                'Done processing.'."\n"
                    .'Total processed stores: %d'."\n",
                $this->_sCount, $this->_iCount
            );

        } catch (Exception $e) {
            echo $e->getMessage().'@'.time();
        }

    }

    // Usage instructions
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f prelaunch.php -- [options]

  --stores <names>       Process only these stores (comma-separated)

  help                   This help

USAGE;
    }

    /**
     * Lets process each store sales
     * @param  string $store Pass in the store to process
     */
    protected function _processStore($store) {
        $storeName = $store->getName();

        printf('Processing "%s" store'."\n", $storeName);

        $this->_sCount++;

        Mage::app()->setCurrentStore($store->getId());

        echo "\n";

        $salesModel = Mage::getModel("sales/order");
        $salesCollection = $salesModel->getCollection();
        foreach($salesCollection as $order)
        {
            if ($order->getCustomerId()) {
                $_order[$order->getIncrementId()]['customer'][$order->getCustomerId()] = array();
                foreach ($order->getAllItems() as $item) {
                    $_order[$order->getIncrementId()]['customer'][$order->getCustomerId()]['items'][] = $item->getProductId();
                }
            }
        }
        // print_r($_order);
        $this->preparePost($_order);

        echo "\n";
    }

    /**
     * Setup customers, products and actions
     * @param  string $orders the order for given store
     */
    private function preparePost($orders) {

        foreach ($orders as $order) {

            foreach ($order['customer'] as $key => $items) {
                $customerId = $key;
                $products = $items['items'];
            }

            $this->_addCustomer($customerId);           
            $this->_addItems($products, $customerId);
        }
    }

    /**
     * Sets up cURL request paramaters for adding a customer
     * @param int $customerId Customer ID of loggedin customer
     */
    private function _addCustomer($customerId) {

        $fields_string = 'pio_appkey='.$this->_key.'&';
        $fields_string .= 'pio_uid='.$customerId;
        $this->postCurl($this->getApiHost().':'.$this->getApiPort().'/'.$this->_userUrl, $fields_string);
    }

    /**
     * Sets up cURL request paramaters for adding a parent 
     * item of ordered product (Since Upsells can only be shown on parents)
     * @param int $productid  Product ID of purchased item
     * @param int $customerId Customer ID of loggedin customer
     */
    private function _addItems($products, $customerId) {

        foreach ($products as $key => $productid) {
            $product = Mage::getModel('catalog/product')->load($productid);         
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
        }

        $fields_string = 'pio_appkey='.$this->_key.'&';
        $fields_string .= 'pio_iid='.$_productId.'&';
        $fields_string .= 'pio_itypes=1';
        $this->postCurl($this->getApiHost().':'.$this->getApiPort().'/'.$this->_itemsUrl, $fields_string);

        $this->_addAction($_productId, $customerId);

    }

    /**
     * Sets up cURL request paramaters for adding a user-to-item action
     * @param int $productid  Product ID of item to action
     * @param int $customerId Customer ID of loggedin customer
     */
    private function _addAction($_productId, $customerId) {

        $fields_string = 'pio_appkey='.$this->_key.'&';
        $fields_string .= 'pio_uid='.$customerId.'&';
        $fields_string .= 'pio_iid='.$_productId.'&';
        $fields_string .= 'pio_action=conversion';
        $this->postCurl($this->getApiHost().':'.$this->getApiPort().'/'.$this->_actionsUrl, $fields_string);        

    }

    /**
     * Perform the cURL POST Request
     * @param  string $url   URL of PredictionIO API 
     * @param  string $fields_string Query params for POST data
     */
    private function postCurl($url, $fields_string) {
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

        // var_dump($result);

        //close connection
        curl_close($ch);
    }

}
// Instantiate
$shell = new Richdynamix_Shell_Similarity();
// Initiate script
$shell->run();