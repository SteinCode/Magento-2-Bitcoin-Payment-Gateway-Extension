<?php
namespace Spectrocoin\Merchant\Controller\StatusPage;

use Spectrocoin\Merchant\Model\Payment as PaymentModel;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\OrderCallback;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Request\Http;

use Psr\Log\LoggerInterface;


class Callback extends Action {
    protected $order;
    protected $paymentModel;
    protected $client;
    protected $httpRequest;
    protected $logger;
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
        Http $request,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->paymentModel = $paymentModel;
        $this->client = $paymentModel->getSCClient();
        $this->httpRequest = $request;
        $this->logger = $logger;
    }


    /**
     * Default customer account page
     * @return void
     */
    public function execute() {
        $order_callback = $this->initCallbackFromPost();

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

    /**
	 * Initializes the callback data from POST request.
	 * 
	 * @return OrderCallback|null Returns an OrderCallback object if data is valid, null otherwise.
	 */
	private function initCallbackFromPost(): ?OrderCallback
	{
		$expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

		$callback_data = [];
		foreach ($expected_keys as $key) {
			if (isset($_POST[$key])) {
				$callback_data[$key] = $_POST[$key];
			}
		}

		if (empty($callback_data)) {
			$this->logger->error('No data received in callback');
			return null;
		}
		return new OrderCallback($callback_data);
	}
}