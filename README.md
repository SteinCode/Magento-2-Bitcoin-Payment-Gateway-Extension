# SpectroCoin Magento 2 Crypto Payment plugin

Integrate cryptocurrency payments seamlessly into your Magento store with the [SpectroCoin Crypto Payment Module](https://spectrocoin.com/en/plugins/magento2.html). This extension facilitates the acceptance of a variety of cryptocurrencies, enhancing payment options for your customers. Easily configure and implement secure transactions for a streamlined payment process on your Wordpress website.

## Installation

1. Access your server terminal.
2. Navigate to the magento web-root.
3. Enter following command:

```bash
composer require 'spectrocoin/magento2merchant'
```

4. To enable plugin run:
```bash
php bin/magento module:enable Spectrocoin_Merchant --clear-static-content
```

5. To register the module run:
```bash
php bin/magento setup:upgrade
```

6. To compile dependency injenction run:
```bash
bin/magento setup:di:compile
```

## Setting up

1. **[Sign up](https://auth.spectrocoin.com/signup)** for a SpectroCoin Account.
2. **[Log in](https://auth.spectrocoin.com/login)** to your SpectroCoin account.
3. On the dashboard, locate the **[Business](https://spectrocoin.com/en/merchants/projects)** tab and click on it.
4. Click on **[New project](https://spectrocoin.com/en/merchants/projects/new)**.
5. Fill in the project details and select desired settings (settings can be changed).
6. Click **"Submit"**.
7. Copy and paste the "Project id".
8. Click on the user icon in the top right and navigate to **[Settings](https://test.spectrocoin.com/en/settings/)**. Then click on **[API](https://test.spectrocoin.com/en/settings/api)** and choose **[Create New API](https://test.spectrocoin.com/en/settings/api/create)**.
9. Add "API name", in scope groups select **"View merchant preorders"**, **"Create merchant preorders"**, **"View merchant orders"**, **"Create merchant orders"**, **"Cancel merchant orders"** and click **"Create API"**.
10. Copy and store "Client id" and "Client secret". Save the settings.

**Note:** Keep in mind that if you want to use the business services of SpectroCoin, your account has to be verified.

## Testing Callbacks

Order callbacks in the SpectroCoin plugin allow your WordPress site to automatically process order status changes sent from SpectroCoin. These callbacks notify your server when an order’s status transitions to PAID, EXPIRED, or FAILED. Understanding and testing this functionality ensures your store handles payments accurately and updates order statuses accordingly.
 
1. Go to your SpectroCoin project settings and enable **Test Mode**.
2. From the __Test mode__ select a payment status (__PAID__ or __EXPIRED__), which will be sent with a callback.
3. Ensure your `callbackUrl` is publicly accessible (local servers like `localhost` will not work).
4. Check the **Order History** in SpectroCoin for callback details. If a callback fails, use the **Retry** button to resend it.
5. Verify that:
   - The **order status** in WordPress has been updated accordingly.
   - The **callback status** in the SpectroCoin dashboard is `200 OK`.

## Contact

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com <br/>
Skype: [spectrocoin_merchant](https://join.skype.com/invite/iyXHU7o08KkW) </br>
[Web](https://spectrocoin.com) </br>
[X (formerly Twitter)](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)

## Changelog

### 2.0.0 MAJOR ():

This major update introduces several improvements, including enhanced security, updated coding standards, and a streamlined integration process. **Important:** Users must generate new API credentials (Client ID and Client Secret) in their SpectroCoin account settings to continue using the plugin. The previous private key and merchant ID functionality have been deprecated.

_Updated_ SCMerchantClient was reworked to adhere to better coding standards.

_Updated_ Order creation API endpoint has been updated for enhanced performance and security.

_Removed_ Private key functionality and merchant ID requirement have been removed to streamline integration.

_Added_ OAuth functionality introduced for authentication, requiring Client ID and Client Secret for secure API access.

_Updated_ Class and some method names have been updated based on PSR-12 standards.

_Updated_ Composer class autoloading has been implemented.

_Added_ _Config.php_ file has been added to store plugin configuration.

_Added_ _Utils.php_ file has been added to store utility functions.

_Added_ _GenericError.php_ file has been added to handle generic errors.

_Added_ Strict types have been added to all classes.

_Migrated_ to GuzzleHttp since HTTPful is no longer maintained. In this case /vendor directory was added which contains GuzzleHttp dependencies.

_Added_ To enhance module security added data sanitization and validation.

_Optimised_ the The whole $\_POST stack processing. Now only needed callback keys is being processed.