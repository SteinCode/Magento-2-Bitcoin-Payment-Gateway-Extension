<?php
namespace Spectrocoin\Merchant\Controller\StatusPage;

use Spectrocoin\Merchant\Model\Payment as PaymentModel;
use Spectrocoin\Merchant\Library\SCMerchantClient\Data\SpectroCoin_OrderCallback;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Request\Http;


class Callback extends Action {
    protected $order;
    protected $paymentModel;
    protected $client;
    protected $httpRequest;

    /**
     * @param Context $context
     * @param Order $order
     * @param PaymentModel $paymentModel
     * @internal param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param Http $request
     */
    public function __construct(
        Context $context,
        Order $order,
        PaymentModel $paymentModel,
        Http $request
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->paymentModel = $paymentModel;
        $this->client = $paymentModel->getSCClient();
        $this->httpRequest = $request;
    }


    /**
     * Default customer account page
     * @return void
     */
    public function execute() {
        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        $post_data = [];
        // TO-DO: check if the request is POST
        foreach ($expected_keys as $key) {
            if (isset($_REQUEST[$key])) {
                $post_data[$key] = $_REQUEST[$key];
            }
        }

        $order_callback = $this->client->spectrocoin_process_callback($post_data);

        if (!is_null($order_callback)) {
            $order = $this->order->loadByIncrementId($order_callback->getOrderId());
            if ($this->paymentModel->updateOrderStatus($order_callback, $order)) {
                $this->getResponse()->setBody('*ok*');
            }
            else {
                $this->getResponse()->setBody('*error*');
            }
        }
        else {
            $this->getResponse()->setBody('*error*');
        }
    }
}