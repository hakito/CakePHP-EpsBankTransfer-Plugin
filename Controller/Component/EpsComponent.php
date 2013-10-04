<?php

App::uses('Component', 'Controller');
App::uses('HttpSocket', 'Network/Http');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

use at\externet\eps_bank_transfer;

class EpsComponent extends Component
{

    public $SoCommunicator;

    /** @var eps_bank_transfer\WebshopArticle[] articles */
    public $Articles = array();

    /** @var int total of amount to pay in cents */
    public $Total = 0;

    /** @var string prefix for caching keys in this component */
    public $CacheKeyPrefix = 'EpsBankTransferComponent';

    /** @var \Controller */
    private $Controller = null;

    public function __construct($collection)
    {
        parent::__construct($collection);
        $defaults = array(
            'SecuritySuffixLength' => 8,                                        
            'SecuritySeed'  => Configure::read('Security.salt'),                        
            );             
        
        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));
        
        $this->SoCommunicator = new eps_bank_transfer\SoCommunicator;
        $this->SoCommunicator->LogCallback = array($this, 'WriteLog');
        $this->SoCommunicator->SecuritySuffixLength = $config['SecuritySuffixLength'];
        $this->SoCommunicator->SecuritySeed = $config['SecuritySeed'];
    }

    public function startup(\Controller $controller)
    {
        parent::startup($controller);
        $this->Controller = $controller;
    }

    /**
     * Add an Webshop Article. The article will be appended to the array or
     * at the given array position.
     * @param string $name
     * @param int $count
     * @param int $price in cents
     * @param string $arrayPosition optional identifier for internal storage
     */
    public function AddArticle($name, $count, $price, $arrayPosition = null)
    {
        $article = new eps_bank_transfer\WebshopArticle($name, $count, $price);
        if ($arrayPosition != null)
            $this->Articles[$arrayPosition] = $article;
        else
            $this->Articles[] = $article;

        $this->Total += (int) $count * (int) $price;
    }

    /**
     * Get banks as associative array. The bank array will be cached.
     * @param type $invalidateCache set to TRUE to force reading not from cache
     * @return array associative array with bank name as key
     */
    public function GetBanksArray($invalidateCache = false)
    {
        $key = $this->CacheKeyPrefix . 'BanksArray';
        $banks = Cache::read($key);
        if (!$banks || $invalidateCache)
        {
            $banks = $this->SoCommunicator->TryGetBanksArray();
            if (!empty($banks))
                Cache::write($key, $banks);
        }
        return $banks;
    }

    /**
     * Redirect to Online Banking
     * @param string $remittanceIdentifier Identifier for the given order. For example Order.id
     * @param string $TransactionOkUrl The url the customer is redirected to if transaction was successful
     * @param string $TransactionNokUrl The url the customer is redirected to if transaction was not successful
     * @param string $bankName optional bank name if the bank was already choosen on the site. If not given
     * the user will be prompted later to select his bank
     * @throws XmlValidationException when the returned BankResponseDetails does not validate against XSD
     * @throws cakephp\SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @return string BankResponseDetails
     * @return array Error info array (ErrorCode, ErrorMsg) from the BankResponseDetails
     */
    public function PaymentRedirect($remittanceIdentifier, $TransactionOkUrl, $TransactionNokUrl, $bankName = null)
    {
        $config = Configure::read('EpsBankTransfer');
        $referenceIdentifier = uniqid($remittanceIdentifier . ' ');
        $transferMsgDetails = new eps_bank_transfer\TransferMsgDetails(
                        Router::url('/eps_bank_transfer/process', true),
                        $TransactionOkUrl,
                        $TransactionNokUrl
        );
        $transferInitiatorDetails = new eps_bank_transfer\TransferInitiatorDetails(
                        $config['userid'],
                        $config['secret'],
                        $config['bic'],
                        $config['account_owner'],
                        $config['iban'],
                        $referenceIdentifier,
                        $remittanceIdentifier,
                        $this->Total,
                        $this->Articles,
                        $transferMsgDetails);
        $bankUrl = null;

        if (!empty($bankName))
        {
            $banks = $this->GetBanksArrayOrNull();
            if ($banks != null)
            {
                $bankUrl = $banks[$bankName]['epsUrl'];
            }
        }

        $logPrefix = 'SendPaymentOrder [' . $referenceIdentifier . ']';

        self::WriteLog($logPrefix . ' over ' . $transferInitiatorDetails->InstructedAmount);
        $plain = $this->SoCommunicator->SendTransferInitiatorDetails($transferInitiatorDetails, $bankUrl);
        $xml = new SimpleXMLElement($plain);
        $soAnswer = $xml->children(eps_bank_transfer\XMLNS_epsp);
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;

        if (('' . $errorDetails->ErrorCode) != '000')
        {
            $errorCode = '' . $errorDetails->ErrorCode;
            $errorMsg = '' . $errorDetails->ErrorMsg;
            self::WriteLog($logPrefix . ' Error ' . $errorCode . ' ' . $errorMsg, false);
            return array(
                'ErrorCode' => $errorCode,
                'ErrorMsg' => $errorMsg
            );
        }

        self::WriteLog($logPrefix, true);
        return $this->Controller->redirect('' . $soAnswer->BankResponseDetails->ClientRedirectUrl);
    }
    
    /**
     * Call this function when the confirmation URL is called by the Scheme Operator.
     * @param string $rawPostStream will read from this stream or file with file_get_contents
     * @param string $outputStream will write to this stream the expected responses for the
     * @throws InvalidCallbackException when callback is not callable
     * @throws CallbackResponseException when callback does not return TRUE
     * @throws XmlValidationException when $rawInputStream does not validate against XSD
     * @throws cakephp\SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @throws UnknownRemittanceIdentifierException when security suffix does not match
     */
    public function HandleConfirmationUrl($rawPostStream = 'php://input', $outputStream = 'php://output')
    {
        $defaults = array(
            'ConfirmationCallback' => 'afterEpsBankTransferNotification', 
            'VitalityCheckCallback' => null,
            );
        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));
        $this->SoCommunicator->HandleConfirmationUrl(
                array($this->Controller, $config['ConfirmationCallback']),
                empty($config['VitalityCheckCallback']) ? null:array($this->Controller, $config['VitalityCheckCallback']), //$config['VitalityCheckCallback'],
                $rawPostStream,
                $outputStream);
    }

    // PRIVATE FUNCTIONS

    private static function WriteLog($message, $success = null)
    {
        if ($success != null)
            $message = $success ? 'SUCCESS: ' : 'FAILED: ' . $message;
        CakeLog::write('eps', $message);
    }

}