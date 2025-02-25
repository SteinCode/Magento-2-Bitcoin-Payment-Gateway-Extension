<?php

declare(strict_types=1);

namespace Spectrocoin\Merchant\Library\SCMerchantClient;

use Spectrocoin\Merchant\Library\SCMerchantClient\Config;
use Spectrocoin\Merchant\Library\SCMerchantClient\Exception\ApiError;
use Spectrocoin\Merchant\Library\SCMerchantClient\Exception\GenericError;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\CreateOrderRequest;
use Spectrocoin\Merchant\Library\SCMerchantClient\Http\CreateOrderResponse;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

use InvalidArgumentException;
use Exception;
use RuntimeException;

class SCMerchantClient
{
    private string $project_id;
    private string $client_id;
    private string $client_secret;

    protected Client $http_client;

    protected ObjectManager $magento_object_manager;
    protected WriterInterface $magento_config_writer;
    protected ScopeConfigInterface $magento_scope_config;
    protected EncryptorInterface $magento_encryptor;

    /**
     * Constructor
     * 
     * @param string $project_id
     * @param string $client_id
     * @param string $client_secret
     */
    public function __construct(string $project_id, string $client_id, string $client_secret)
    {
        $this->project_id = $project_id;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        $this->http_client = new Client();

        $this->magento_object_manager = ObjectManager::getInstance();
        $this->magento_config_writer = $this->magento_object_manager->get(WriterInterface::class);
        $this->magento_scope_config = $this->magento_object_manager->get(ScopeConfigInterface::class);
        $this->magento_encryptor = $this->magento_object_manager->get(EncryptorInterface::class);        
    }

    /**
     * Create an order
     * 
     * @param array $order_data
     * @return CreateOrderResponse|ApiError|GenericError|null
     */
    public function createOrder(array $order_data)
    {
        $access_token_data = $this->getAccessTokenData();

        if (!$access_token_data || $access_token_data instanceof ApiError) {
            return $access_token_data;
        }

        try {
            $create_order_request = new CreateOrderRequest($order_data);
        } catch (InvalidArgumentException $e) {
            return new GenericError($e->getMessage(), $e->getCode());
        }

        $order_payload = $create_order_request->toArray();
        $order_payload['projectId'] = $this->project_id;

        return $this->sendCreateOrderRequest(json_encode($order_payload));
    }

    /**
     * Send create order request
     * 
     * @param string $order_payload
     * @return CreateOrderResponse|ApiError|GenericError
     */
    private function sendCreateOrderRequest(string $order_payload)
    {
        try {
            $response = $this->http_client->request('POST', Config::MERCHANT_API_URL . '/merchants/orders/create', [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $this->getAccessTokenData()['access_token'],
                    'Content-Type' => 'application/json'
                ],
                RequestOptions::BODY => $order_payload
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Failed to parse JSON response: ' . json_last_error_msg());
            }

            $responseData = [
                'preOrderId' => $body['preOrderId'] ?? null,
                'orderId' => $body['orderId'] ?? null,
                'validUntil' => $body['validUntil'] ?? null,
                'payCurrencyCode' => $body['payCurrencyCode'] ?? null,
                'payNetworkCode' => $body['payNetworkCode'] ?? null,
                'receiveCurrencyCode' => $body['receiveCurrencyCode'] ?? null,
                'payAmount' => $body['payAmount'] ?? null,
                'receiveAmount' => $body['receiveAmount'] ?? null,
                'depositAddress' => $body['depositAddress'] ?? null,
                'memo' => $body['memo'] ?? null,
                'redirectUrl' => $body['redirectUrl'] ?? null
            ];

            return new CreateOrderResponse($responseData);
        } catch (InvalidArgumentException $e) {
            return new GenericError($e->getMessage(), $e->getCode());
        } catch (RequestException $e) {
            return new ApiError($e->getMessage(), $e->getCode());
        } catch (Exception $e) {
            return new GenericError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Retrieves the current access token data
     * 
     * @return array|null
     */
    public function getAccessTokenData()
    {
        $current_time = time();
        $encrypted_access_token_data = $this->magento_scope_config->getValue(Config::ACCESS_TOKEN_CONFIG_PATH);
        if ($encrypted_access_token_data) {
            $access_token_data = json_decode($this->magento_encryptor->decrypt($encrypted_access_token_data), true);
            if ($this->isTokenValid($access_token_data, $current_time)) {
                return $access_token_data;
            }
        }
        return $this->refreshAccessToken($current_time);
    }

    /**
     * Refreshes the access token
     * 
     * @param int $current_time
     * @return array|null
     * @throws RequestException
     */
    public function refreshAccessToken(int $current_time)
    {
        try {
            $response = $this->http_client->post(Config::AUTH_URL, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                ],
            ]);

            $access_token_data = json_decode((string) $response->getBody(), true);
            if (!isset($access_token_data['access_token'], $access_token_data['expires_in'])) {
                return new ApiError('Invalid access token response');
            }

            $access_token_data['expires_at'] = $current_time + $access_token_data['expires_in'];
	
			$encrypted_access_token_data = $this->magento_encryptor->encrypt(json_encode($access_token_data));

            $this->magento_config_writer->save(Config::ACCESS_TOKEN_CONFIG_PATH, $encrypted_access_token_data);

			return $access_token_data;

        } catch (RequestException $e) {
            return new ApiError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Checks if the current access token is valid
     * 
     * @param array $access_token_data
     * @param int $current_time
     * @return bool
     */
    private function isTokenValid(array $access_token_data, int $current_time): bool
    {
        return isset($access_token_data['expires_at']) && $current_time < $access_token_data['expires_at'];
    }
}
