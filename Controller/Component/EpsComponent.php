<?php

App::uses('Component', 'Controller');
App::uses('HttpSocket', 'Network/Http');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

use at\externet\eps_bank_transfer;
//require_once EPS_BANK_TRANSFER_APP . 'Lib' . DS . 'EPS' . DS . 'at' . DS . 'externet' . DS . 'eps_bank_transfer' . DS . 'functions.php';

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
        $this->SoCommunicator = new eps_bank_transfer\SoCommunicator;
        $this->SoCommunicator->LogCallback = array($this, 'WriteLog');
    }
    
    public function startup(\Controller $controller)
    {
        parent::startup($controller);
        $this->Controller = $controller;
    }

    /**
     * Add an article
     * @param string $name
     * @param int $count
     * @param int $price in cents
     */
    public function AddArticle($name, $count, $price, $identifier = null)
    {
        $article = new eps_bank_transfer\WebshopArticle($name, $count, $price);
        if ($identifier != null)
            $this->Articles[$identifier] = $article;
        else
            $this->Articles[] = $article;

        $this->Total += (int) $count * (int) $price;
    }

    /**
     * Get banks as associative array
     * @param type $invalidateCache
     * @return array
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
     * @param string $remittanceIdentifier Identifier for the given order. For example shopping Order.id
     * @param string $TransactionOkUrl The url the customer is redirected to if transaction was successful
     * @param string $TransactionNokUrl The url the customer is redirected to if transaction was not successful
     * @param string $bankName optional bank name if the bank was already choosen on the site. If not given
     * the user will be prompted later to select his bank
     * @return array Error info array (ErrorCode, ErrorMsg) if the redirect failed
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
        
        self::WriteLog($logPrefix . ' over ' . $transferInitiatorDetails->InstructedAmount );
        $plain = $this->SoCommunicator->SendTransferInitiatorDetails($transferInitiatorDetails, $bankUrl);
        $xml = new SimpleXMLElement($plain);
        $soAnswer = $sxml->children(eps_bank_transfer\XMLNS_epsp);
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;
        
        if (('' . $errorDetails->ErrorCode) != '000')
        {
            $errorCode = '' . $errorDetails->ErrorCode;
            $errorMsg = '' . $errorDetails->ErrorMsg;
            self::WriteLog($logPrefix .' Error ' . $errorCode . ' ' . $errorMsg, false);
            return array(
                'ErrorCode' => $errorCode,
                'ErrorMsg' => $errorMsg
                );
        }
        
        self::WriteLog($logPrefix, true);
        return $this->Controller->redirect('' . $soAnswer->BankResponseDetails->ClientRedirectUrl);
    }

    private static function WriteLog($message, $success = null)
    {
        if ($success != null)
            $message = $success ? 'SUCCESS: ' : 'FAILED: ' . $message;
        CakeLog::write('eps', $message);
    }
}