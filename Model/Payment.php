<?php
declare(strict_types=1);

namespace Spectrocoin\Merchant\Model;

use Spectrocoin\Merchant\Library\SCMerchantClient\SCMerchantClient;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\CreateOrderRequest;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\CreateOrderResponse;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\OrderCallback;
use Spectrocoin\Merchant\Library\SCMerchantClient\Exception\GenericError;
use Spectrocoin\Merchant\Library\SCMerchantClient\Exception\ApiError;
use Spectrocoin\Merchant\Library\SCMerchantClient\Enum\OrderStatus;

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
use Magento\Framework\App\ObjectManager;

use Exception;

class Payment extends AbstractMethod {
    const CODE = 'spectrocoin_merchant';
    protected $_code = 'spectrocoin_merchant';
    protected UrlInterface $url_builder;
    protected StoreManagerInterface $store_manager;
    protected SCMerchantClient $sc_merchant_client;

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
        array $data = []
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

        $this->sc_merchant_client = new SCMerchantClient(
            $this->getConfigData('api_fields/merchant_id'),
            $this->getConfigData('api_fields/client_id'),
            $this->getConfigData('api_fields/client_secret')
        );

        $this->url_builder = $url_builder;
        $this->store_manager = $store_manager;
    }

    /**
     * @return SCMerchantClient
     */
    public function getSCClient(): SCMerchantClient {
        return $this->sc_merchant_client;
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getSpectrocoinResponse(Order $order): array {
        $description = [];
        foreach ($order->getAllItems() as $item) {
            $description[] = number_format((float)$item->getQtyOrdered(), 0) . ' × ' . $item->getName();
        }
        $description = implode(', ', $description);

        $order_data = [
            'orderId' => $order->getIncrementId(),
            'description' => $description,
            'receiveAmount' => $order->getGrandTotal(),
            'receiveCurrencyCode' => $order->getOrderCurrencyCode(),
            'callbackUrl' => $this->url_builder->getUrl('spectrocoin/statusPage/callback'),
            'successUrl' => $this->url_builder->getUrl('checkout/onepage/success'),
            'failureUrl' => $this->url_builder->getUrl('checkout/onepage/failure'),
        ];

        $response = $this->sc_merchant_client->createorder($order_data);

        if ($response instanceof CreateOrderResponse) {
            return [
                'status' => 'ok',
                'redirect_url' => $response->getRedirectUrl()
            ];
        } elseif ($response instanceof ApiError || $response instanceof GenericError) {
            return [
                'status' => 'error',
                'errorCode' => $response->getCode(),
                'errorMsg' => $response->getMessage()
            ];
        } else {
            return [
                'status' => 'error',
                'errorCode' => 1,
                'errorMsg' => 'Unknown SpectroCoin error'
            ];
        }
    }

    /**
     * Returns order status from configuration
     * @param string $config_option
     * @param string $default_value
     * @return string
     */
    protected function getStatusDataOrDefault(string $config_option, string $default_value = 'pending'): string {
        $data = $this->getConfigData($config_option);
        if (!$data) {
            $data = $default_value;
        }

        return $data;
    }

    /**
     * Returns order status mapped to spectrocoin status
     * @param string $spectrocoin_status
     * @return string
     */
    protected function getOrderStatus(string $spectrocoin_status): string {
        switch($spectrocoin_status) {
            case OrderStatus::New->value:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_new',
                    'new'
                );
                break;
            case OrderStatus::Expired->value:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_expired',
                    'canceled'
                );
                break;
            case OrderStatus::Failed->value:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_failed',
                    'closed'
                );
                break;
            case OrderStatus::Paid->value:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_paid',
                    'complete'
                );
                break;
            case OrderStatus::Pending->value:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_pending',
                    'pending_payment'
                );
                break;
            default:
                $status_option = $this->getStatusDataOrDefault(
                    'payment_settings/order_status_pending',
                    'pending_payment'
                );
        }
        return $status_option;
    }

    public function updateOrderStatus(OrderCallback $callback, Order $order): bool {
        try {
            $order_state = $this->getOrderStatus($callback->getStatus());

            $order
                ->setState($order_state, true)
                ->setStatus($order->getConfig()->getStateDefaultStatus($order_state))
                ->save();
            return true;
        } catch (Exception $e) {
            exit('Error occurred: ' . $e);
        }
    }
}
