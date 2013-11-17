<?php

App::uses('Component', 'Controller');
App::uses('HttpSocket', 'Network/Http');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

use at\externet\eps_bank_transfer;

class EpsComponent extends Component
{
    /** 
     * Webshop articles
     * @var eps_bank_transfer\WebshopArticle[]  */
    public $Articles = array();

    /**
     * Total of amount to pay in cents 
     * @var int 
     */
    public $Total = 0;

    /** @var \Controller */
    private $Controller = null;

    /** @var string */
    private $ObscuritySeed;

    public function __construct($collection)
    {
        parent::__construct($collection);
        $defaults = array(
            'ObscuritySuffixLength' => 8,
            'ObscuritySeed'  => Configure::read('Security.salt'),
            );             
        
        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));

        $SoCommunicator = EpsCommon::GetSoCommunicator();
        $SoCommunicator->ObscuritySuffixLength = $config['ObscuritySuffixLength'];
        $SoCommunicator->ObscuritySeed = $this->ObscuritySeed = $config['ObscuritySeed'];
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
     * @param bool $invalidateCache set to TRUE to force reading not from cache
     * @param string $config cache config used for caching
     * @return array associative array with bank name as key
     */
    public function GetBanksArray($invalidateCache = false, $config = 'default')
    {
        return EpsCommon::GetBanksArray($invalidateCache, $config);
    }

    /**
     * Redirect to Online Banking
     * @param string $remittanceIdentifier Identifier for the given order. For example Order.id
     * @param string $TransactionOkUrl The url the customer is redirected to if transaction was successful
     * @param string $TransactionNokUrl The url the customer is redirected to if transaction was not successful
     * @param string $bic optional bank BIC if the bank was already choosen on the site. If not given
     * the user will be prompted later to select his bank
     * @throws XmlValidationException when the returned BankResponseDetails does not validate against XSD
     * @throws cakephp\SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @return string BankResponseDetails
     * @return array Error info array (ErrorCode, ErrorMsg) from the BankResponseDetails
     */
    public function PaymentRedirect($remittanceIdentifier, $TransactionOkUrl, $TransactionNokUrl, $bic = null)
    {
        $config = Configure::read('EpsBankTransfer');
        $referenceIdentifier = uniqid($remittanceIdentifier . ' ');
        $eRemittanceIdentifier= Security::rijndael($remittanceIdentifier, $this->ObscuritySeed, 'encrypt');
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
        
        $transferInitiatorDetails->OrderingCustomerOfiIdentifier = $bic;
        
        $logPrefix = 'SendPaymentOrder [' . $referenceIdentifier . ']';

        EpsCommon::WriteLog($logPrefix . ' over ' . $transferInitiatorDetails->InstructedAmount);
        $plain = EpsCommon::GetSoCommunicator()->SendTransferInitiatorDetails($transferInitiatorDetails);
        $xml = new SimpleXMLElement($plain);
        $soAnswer = $xml->children(eps_bank_transfer\XMLNS_epsp);
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;

        if (('' . $errorDetails->ErrorCode) != '000')
        {
            $errorCode = '' . $errorDetails->ErrorCode;
            $errorMsg = '' . $errorDetails->ErrorMsg;
            EpsCommon::WriteLog($logPrefix . ' Error ' . $errorCode . ' ' . $errorMsg, false);
            return array(
                'ErrorCode' => $errorCode,
                'ErrorMsg' => $errorMsg
            );
        }

        EpsCommon::WriteLog($logPrefix, true);
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
    public function HandleConfirmationUrl($eRemittanceIdentifier, $rawPostStream = 'php://input', $outputStream = 'php://output')
    {
        EpsCommon::WriteLog('Handle confirmation url');
        $defaults = array(
            'ConfirmationCallback' => 'afterEpsBankTransferNotification', 
            'VitalityCheckCallback' => null,
            );
        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));

        $remittanceIdentifier = Security::rijndael($eRemittanceIdentifier, $this->ObscuritySeed, 'decrypt');
        $controller = &$this->Controller;

        $confirmationCallbackWrapper = function($raw, $xmlRemittanceIdentifier, $statusCode) use ($config, $remittanceIdentifier, &$controller)
                {
                    if ($remittanceIdentifier != $xmlRemittanceIdentifier)
                        throw new eps_bank_transfer\UnknownRemittanceIdentifierException('Remittance identifier mismatch');

                    return call_user_func_array(array($controller, $config['ConfirmationCallback']), array($raw, $xmlRemittanceIdentifier, $statusCode));
                };

        EpsCommon::GetSoCommunicator()->HandleConfirmationUrl(
                $confirmationCallbackWrapper,
                empty($config['VitalityCheckCallback']) ? null:array($this->Controller, $config['VitalityCheckCallback']),
                $rawPostStream,
                $outputStream);
    }


}