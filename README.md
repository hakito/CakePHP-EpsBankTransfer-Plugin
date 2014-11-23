[![Build Status](https://travis-ci.org/hakito/CakePHP-EpsBankTransfer-Plugin.svg?branch=master)](https://travis-ci.org/hakito/CakePHP-EpsBankTransfer-Plugin)
[![Coverage Status](https://coveralls.io/repos/hakito/CakePHP-EpsBankTransfer-Plugin/badge.png?branch=master)](https://coveralls.io/r/hakito/CakePHP-EpsBankTransfer-Plugin?branch=master)
[![Latest Stable Version](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/v/stable.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![Total Downloads](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/downloads.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![Latest Unstable Version](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/v/unstable.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin) [![License](https://poser.pugx.org/hakito/cakephp-stuzza-eps-banktransfer-plugin/license.svg)](https://packagist.org/packages/hakito/cakephp-stuzza-eps-banktransfer-plugin)

CakePHP-EpsBankTransfer-Plugin
==============================

CakePHP e-payment standard plugin

Installation
------------

### Using composer

If you are using composer simply add the following requirement to your composer file:

```json
{
    "require": { "hakito/cakephp-stuzza-eps-banktransfer-plugin": "dev-master" }
}
```

### Without composer

Download the plugin to app/Plugin/EpsBankTransfer. Also download https://github.com/hakito/PHP-Stuzza-EPS-BankTransfer
Add a PSR-4 compatible autoloader to your bootstrap.

Confguration
------------

Load the Plugin in yor bootstrap.php

```php
CakePlugin::load('EpsBankTransfer', array('routes' => true));

// If you want to collect the log stream configure a logging scope for 'eps':
CakeLog::config('eps', array(
	'engine' => 'FileLog',
	'scopes' => array('eps'),
));
```

Add the following config to your core.php

```php
Configure::write('EpsBankTransfer', array(
    // required parameters
    'userid' => 'AKLJS231534', // Eps "Händler" id
    'secret' => 'topSecret', // Secret for authentication
    'iban' => 'AT611904300234573201', // IBAN code of bank account where money will be sent to
    'bic' => 'GAWIATW1XXX', // BIC code of bank account where money will be sent to
    'account_owner' => 'John Q. Public', // Name of the account owner where money will be sent to

    //// optional parameters
    //'ObscuritySuffixLength' => 8,                            // Number of hash chars appended to remittance identifier
    //'ObscuritySeed'  => Configure::read('Security.salt'),    // Hash seed or suffix of remittance identifier
    //'ConfirmationCallback' => 'afterEpsBankTransferNotification', // Name of callback function to be called in app controller when confirmation url is called with bankconfirmation details
    //'VitalityCheckCallback' => null                         // Name of callback function to be called when confirmation url is called with vitalitycheck details
));
```
