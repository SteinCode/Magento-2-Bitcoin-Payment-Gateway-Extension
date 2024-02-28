<?php
namespace Spectrocoin\Merchant\Model;

use Braintree\Exception;
use Spectrocoin\Merchant\Library\SCMerchantClient\Data\SpectroCoin_OrderCallback;
use Spectrocoin\Merchant\Library\SCMerchantClient\SCMerchantClient;
use Spectrocoin\Merchant\Library\SCMerchantClient\Message\SpectroCoin_CreateOrderRequest;
use Spectrocoin\Merchant\Library\SCMerchantClient\Message\SpectroCoin_CreateOrderResponse;
use Spectrocoin\Merchant\Library\SCMerchantClient\Data\SpectroCoin_ApiError;
use Spectrocoin\Merchant\Library\SCMerchantClient\Data\SpectroCoin_OrderStatusEnum;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;


class Payment extends AbstractMethod {
    const COINGATE_MAGENTO_VERSION = '1.0.6';
    const CODE = 'spectrocoin_merchant';
    protected $_code = 'spectrocoin_merchant';
    // protected $_isInitializeNeeded = true;
    protected $url_builder;
    protected $store_manager;
    protected $scClient;
    protected $resolver;


    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extension_factory
     * @param AttributeValueFactory $custom_attribute_factory
     * @param Data $payment_data
     * @param ScopeConfigInterface $scope_config
     * @param Logger $logger
     * @param UrlInterface $url_builder
     * @param StoreManagerInterface $store_manager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resource_collection
     * @param array $data
     * @internal param ModuleListInterface $moduleList
     * @internal param TimezoneInterface $localeDate
     * @internal param CountryFactory $countryFactory
     * @internal param Http $response
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extension_factory,
        AttributeValueFactory $custom_attribute_factory,
        Data $payment_data,
        ScopeConfigInterface $scope_config,
        Logger $logger,
        UrlInterface $url_builder,
        StoreManagerInterface $store_manager,
        AbstractResource $resource = null,
        AbstractDb $resource_collection = null,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extension_factory,
            $custom_attribute_factory,
            $payment_data,
            $scope_config,
            $logger,
            $resource,
            $resource_collection,
            $data
        );

        $this->scClient = new SCMerchantClient(
            $this->getConfigData('api_fields/api_url'),
            $this->getConfigData('api_fields/auth_url'),
            $this->getConfigData('api_fields/merchant_id'),
            $this->getConfigData('api_fields/client_id'),
            $this->getConfigData('api_fields/client_secret'),
        );

        $this->url_builder = $url_builder;
        $this->store_manager = $store_manager;
    }


    /**
     * @return SCMerchantClient
     */
    public function getSCClient() {
        return $this->scClient;
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getSpectrocoinResponse(Order $order) {

        $order_id = $order->getIncrementId();
        $receive_currency_code = $order->getOrderCurrencyCode();
        $pay_currency_code = 'BTC';

        $callback_url = $this->url_builder->getUrl('spectrocoin/statusPage/callback');
        $success_url =  $this->url_builder->getUrl('checkout/onepage/success');
        $failure_url =  $this->url_builder->getUrl('checkout/onepage/failure');
        $receive_amount = number_format($order->getGrandTotal(), 2, '.', '');

        $description = array();
        foreach ($order->getAllItems() as $item) {
            $description[] = number_format($item->getQtyOrdered(), 0) . ' × ' . $item->getName();
        }

        $description = implode(', ', $description);
        $description = '';

        // TO-DO: should be loaded via DI, but today it doesn't work
        try {
            $locale = explode('_', \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\Locale\Resolver')->getLocale())[0];
        }
        catch (\Exception $e) {
            $locale = 'en';
        }
        // TO-DO: test, because previously it was parsing only fiat currency, now it parses fiat and btc
        if ($this->getConfigData('payment_settings/order_payment_method') == 'pay') {
            $order_request = new SpectroCoin_CreateOrderRequest(
                $order_id,
                $description,
                $receive_amount,
                $receive_currency_code,
                null,
                $pay_currency_code,
                $callback_url,
                $success_url,
                $failure_url,
                $locale
            );
        }
        else {
            $order_request = new SpectroCoin_CreateOrderRequest(
                $order_id,
                $description,
                null,
                $receive_currency_code,
                $receive_amount,
                $pay_currency_code,
                $callback_url,
                $success_url,
                $failure_url,
                $locale
            );
        }

        try {
            $response = $this->scClient->spectrocoin_create_order($order_request);
        }
        catch (Exception $e) {
            return [
                'status' => 'error',
                'errorCode' => 1,
                'errorMsg' => 'Error: '.$e->getMessage()
            ];
        }

        if($response instanceof SpectroCoin_CreateOrderResponse) {
            return [
                'status' => 'ok',
                'redirect_url' => $response->getRedirectUrl()
            ];
        }
        elseif($response instanceof SpectroCoin_ApiError) {
            return [
                'status' => 'error',
                'errorCode' => $response->getCode(),
                'errorMsg' => $response->getMessage()
            ];
        }
        else {
            return [
                'status' => 'error',
                'errorCode' => 1,
                'errorMsg' => 'Unknown Spectrocoin error'
            ];
        }
    }

    /**
     * Returns order status from configuration
     * @param string $config_option
     * @param string $default_value
     * @return mixed|string
     */
    protected function getStatusDataOrDefault($config_option, $default_value = 'pending') {
        $data = $this->getConfigData($config_option);
        if (!$data) {
            $data = $default_value;
        }

        return $data;
    }

    /**
     * Returns order status mapped to spectrocoin status
     * @param string $spectrocoin_status
     * @return mixed|string
     */
    protected function getOrderStatus($spectrocoin_status) {
        switch($spectrocoin_status) {
            case SpectroCoin_OrderStatusEnum::$New:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_new',
                    'new'
                );
                break;

            case SpectroCoin_OrderStatusEnum::$Expired:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_expired',
                    'canceled'
                );
                break;

            case SpectroCoin_OrderStatusEnum::$Failed:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_failed',
                    'closed'
                );
                break;

            case SpectroCoin_OrderStatusEnum::$Paid:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_paid',
                    'complete'
                );
                break;

            case SpectroCoin_OrderStatusEnum::$Pending:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_pending',
                    'pending_payment'
                );
                break;

            case SpectroCoin_OrderStatusEnum::$Test:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_test',
                    'payment_review'
                );
                break;

            default:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_test',
                    'pending_payment'
                );
        }

        return $status_option;
    }

    public function updateOrderStatus(SpectroCoin_OrderCallback $callback, Order $order) {
        try {
            $order_state = $this->getOrderStatus($callback->getStatus());

            $order
                ->setState($order_state, true)
                ->setStatus($order->getConfig()->getStateDefaultStatus($order_state))
                ->save();
            return true;
        }
        catch (\Exception $e) {
            exit('Error occurred: ' . $e);
        }
    }

}