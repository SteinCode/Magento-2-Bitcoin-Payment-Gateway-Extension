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

7. Navigate to the plugin file directory and install dependencies:

```bash
cd app/code/Spectrocoin/Merchant
```

```bash
composer install
```

## Contact

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com <br/>
Skype: [spectrocoin_merchant](https://join.skype.com/invite/iyXHU7o08KkW) </br>
[Web](https://spectrocoin.com) </br>
[X (formerly Twitter)](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)

## Changelog

### 2.0.0 MAJOR ():


