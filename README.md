[![Build Status](https://travis-ci.org/hakito/CakePHP-EpsBankTransfer-Plugin.svg?branch=master)](https://travis-ci.org/hakito/CakePHP-EpsBankTransfer-Plugin)
[![Coverage Status](https://coveralls.io/repos/hakito/CakePHP-EpsBankTransfer-Plugin/badge.png?branch=master)](https://coveralls.io/r/hakito/CakePHP-EpsBankTransfer-Plugin?branch=master)
[![Latest Stable Version](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/v/stable.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![Total Downloads](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/downloads.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![Latest Unstable Version](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/v/unstable.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![License](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/license.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin)

# CakePHP-EpsBankTransfer-Plugin

CakePHP 4.x plugin

# Installation

## Using composer

If you are using composer simply add the plugin using the command

```bash
composer require hakito/cakephp-stuzza-eps-banktransfer-plugin
```

## Without composer

Download the plugin to app/Plugin/EpsBankTransfer. Also download https://github.com/hakito/PHP-Stuzza-EPS-BankTransfer
Add a PSR-4 compatible autoloader to your bootstrap.

## Load the plugin

Load the plugin in your bootstrap:

```php
public function bootstrap()
{
    // Call parent to load bootstrap from files.
    parent::bootstrap();

    $this->addPlugin(\EpsBankTransfer\Plugin::class, ['routes' => true]);
}
```

# Confguration

In your app.local.php add an entry for EpsBankTransfer

```php
[
    'EpsBankTransfer',
    [
        // required parameters
        'userid' => 'AKLJS231534',           // Eps "Händler" id
        'secret' => 'topSecret',             // Secret for authentication
        'iban' => 'AT611904300234573201',    // IBAN code of bank account where money will be sent to
        'bic' => 'GAWIATW1XXX',              // BIC code of bank account where money will be sent to
        'account_owner' => 'John Q. Public', // Name of the account owner where money will be sent to

        // Encryption key for sending encrypted remittance identifier as encrypted string
        'encryptionKey' => 'A_SECRET_KEY_MUST_BE_32_BYTES_LONG',

        //// optional parameters
        //'ObscuritySuffixLength' => 8,             // Number of hash chars appended to remittance identifier
        //'ObscuritySeed'  => 'SOME RANDOM STRING', // Seed for the random remittance identifier suffix. REQUIRED when ObscuritySuffixLength > 0 provided
        //'TestMode' => true                        // Use EPS test mode URL endpoint
    ]
];
```

## Logs

If you want to collect the log stream add this entry to your log configuration in app_local.php

```php
    'Log' =>
    [
        'eps' =>
        [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'eps',
            'scopes' => ['EpsBankTransfer'],
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency', 'info', 'debug'],
        ],
    ]
```

# Usage

In your payment handling controller:

```php
    // Load the component
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('EpsBankTransfer.Eps');
    }

    // Sample checkout
    private function _checkoutEPS($orderId)
    {
        // Add all articles
        $this->Eps->AddArticle('Magic dragon', $quantity, $priceInCents);

        // You might also want to add shipping agio as article
        $this->Eps->AddArticle('Shipping agio', 1, $shippingAgioInCents);

        // remittanceIdentifier could be your shopping card id
        // okUrl is the return url if payment is successful
        // nOkUrl is the return url if payment failed / canceled
        // BIC of the bank from GetBanksArray
        $this->Eps->PaymentRedirect($remittanceIdentifier, $okUrl, $nOkUrl, $bic);
    }
```

## Event handlers

You must implement at least the following eventhandlers:

### EpsBankTransfer.VitalityCheck

```php
\Cake\Event\EventManager::instance()->on('EpsBankTransfer.VitalityCheck',
function ($event, $args)
{
  // $args =
  // [
  //   'raw' => {string},                  // Raw XML content
  //   'vitalityCheckDetails' => {object}, // Instance of at\externet\eps_bank_transfer\VitalityCheckDetails
  // ]

  return ['handled' => true]; // You have to set this otherwise the EPS call is not successful
});
```

### EpsBankTransfer.Confirmation', $this

```php
\Cake\Event\EventManager::instance()->on('EpsBankTransfer.Confirmation',
function ($event, $args)
{
  // $args =
  // [
  //   'raw' => {string},                     // Raw XML content
  //   'bankConfirmationDetails' => {object}, // Instance of at\externet\eps_bank_transfer\BankConfirmationDetails
  // ]

  return ['handled' => true]; // You have to set this otherwise the EPS call is not successful
});
```
