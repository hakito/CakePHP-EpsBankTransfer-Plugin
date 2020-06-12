<?php
return
[
    'EpsBankTransfer' =>
    [
        // required parameters
        'userid' => 'AKLJS231534', // Eps "Händler" id
        'secret' => 'topSecret', // Secret for authentication
        'iban' => 'AT611904300234573201', // IBAN code of bank account where money will be sent to
        'bic' => 'GAWIATW1XXX', // BIC code of bank account where money will be sent to
        'account_owner' => 'John Q. Public', // Name of the account owner where money will be sent to

        // Encryption key for sending encrypted remittance identifier as encrypted string
        'encryptionKey' => 'A_SECRET_KEY_MUST_BE_32_BYTES_LONG',

        //// optional parameters
        //'ObscuritySuffixLength' => 8,                            // Number of hash chars appended to remittance identifier
        //'ObscuritySeed'  => Configure::read('Security.salt'),    // Hash seed or suffix of remittance identifier
    ]
];