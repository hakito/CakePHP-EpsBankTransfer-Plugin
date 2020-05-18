<?php

/** @noinspection PhpInconsistentReturnPointsInspection
 */
namespace EpsBankTransfer\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\Utility\Security;

use at\externet\eps_bank_transfer;

use EpsBankTransfer\Plugin;
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
    private $EncryptionKey;

    public function initialize(array $config)
    {
        parent::initialize($config);
        $defaults = array(
            'ObscuritySuffixLength' => 8,
            'ObscuritySeed'  => 'c2af496ecf4b9f095447a7b9f5c02d20924252bd',
            );

        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));

        $SoCommunicator = Plugin::GetSoCommunicator();
        $SoCommunicator->ObscuritySuffixLength = $config['ObscuritySuffixLength'];
        $SoCommunicator->ObscuritySeed = $config['ObscuritySeed'];

        if (empty($config['encryptionKey']))
            throw new \InvalidArgumentException('No encryptionKey given in config');

        $this->EncryptionKey = $config['encryptionKey'];
    }

    public function startup($event)
    {
        $this->Controller = $event->getSubject();
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
        return Plugin::GetBanksArray($invalidateCache, $config);
    }

    /**
     * Redirect to Online Banking
     * @param string $remittanceIdentifier Identifier for the given order. For example Order.id
     * @param string $TransactionOkUrl The url the customer is redirected to if transaction was successful
     * @param string $TransactionNokUrl The url the customer is redirected to if transaction was not successful
     * @param string $bic optional bank BIC if the bank was already choosen on the site. If not given
     * @param int $expirationMinutes expiration of payment in minutes. Must be between 5 and 60
     * the user will be prompted later to select his bank
     * @throws \XmlValidationException when the returned BankResponseDetails does not validate against XSD
     * @throws \SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @return string BankResponseDetails
     * @return array Error info array (ErrorCode, ErrorMsg) from the BankResponseDetails
     */
    public function PaymentRedirect($remittanceIdentifier, $TransactionOkUrl, $TransactionNokUrl, $bic = null, $expirationMinutes = null)
    {
        $config = Configure::read('EpsBankTransfer');
        $referenceIdentifier = uniqid($remittanceIdentifier . ' ');

        $eRemittanceIdentifier= rawurlencode(Plugin::Base64Encode(Security::encrypt($remittanceIdentifier, $this->EncryptionKey)));
        $confirmationUrl = Router::url(['controller' => 'PaymentNotifications', 'plugin' => 'EpsBankTransfer', 'action' => 'process'], true).$eRemittanceIdentifier;
        $transferMsgDetails = new eps_bank_transfer\TransferMsgDetails(
                        $confirmationUrl,
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
                        $this->Total,
                        $transferMsgDetails);

        $transferInitiatorDetails->RemittanceIdentifier = $remittanceIdentifier;
        $transferInitiatorDetails->WebshopArticles = $this->Articles;
        $transferInitiatorDetails->OrderingCustomerOfiIdentifier = $bic;
        if ($expirationMinutes != null)
            $transferInitiatorDetails->SetExpirationMinutes($expirationMinutes);

        $logPrefix = 'SendPaymentOrder [' . $referenceIdentifier . '] ConfUrl: ' . $confirmationUrl;

        Plugin::WriteLog($logPrefix . ' over ' . $transferInitiatorDetails->InstructedAmount);
        $plain = Plugin::GetSoCommunicator()->SendTransferInitiatorDetails($transferInitiatorDetails);
        $xml = new \SimpleXMLElement($plain);
        $soAnswer = $xml->children(eps_bank_transfer\XMLNS_epsp);
        /** @noinspection PhpUndefinedFieldInspection */
        $errorDetails = &$soAnswer->BankResponseDetails->ErrorDetails;

        if (('' . $errorDetails->ErrorCode) != '000')
        {
            $errorCode = '' . $errorDetails->ErrorCode;
            $errorMsg = '' . $errorDetails->ErrorMsg;
            Plugin::WriteLog("FAILED: " . $logPrefix . ' Error ' . $errorCode . ' ' . $errorMsg);
            return array(
                'ErrorCode' => $errorCode,
                'ErrorMsg' => $errorMsg
            );
        }

        Plugin::WriteLog("SUCCESS: " . $logPrefix);
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        /** @noinspection PhpUndefinedFieldInspection */
        return $this->Controller->redirect('' . $soAnswer->BankResponseDetails->ClientRedirectUrl);
    }

    /**
     * Call this function when the confirmation URL is called by the Scheme Operator.
     * @param string $eRemittanceIdentifier encrypted remittance identifier
     * @param string $rawPostStream will read from this stream or file with file_get_contents
     * @param string $outputStream will write to this stream the expected responses for the
     * @throws InvalidCallbackException when callback is not callable
     * @throws CallbackResponseException when callback does not return TRUE
     * @throws XmlValidationException when $rawInputStream does not validate against XSD
     * @throws \SocketException when communication with SO fails
     * @throws \UnexpectedValueException when using security suffix without security seed
     * @throws UnknownRemittanceIdentifierException when security suffix does not match
     */
    public function HandleConfirmationUrl($eRemittanceIdentifier, $rawPostStream = 'php://input', $outputStream = 'php://output')
    {
        Plugin::WriteLog('BEGIN: Handle confirmation url');
        $defaults = array(
            'ConfirmationCallback' => 'afterEpsBankTransferNotification',
            'VitalityCheckCallback' => null,
            );
        $config = array_merge($defaults, Configure::read('EpsBankTransfer'));

        $remittanceIdentifier = Security::decrypt(Plugin::Base64Decode($eRemittanceIdentifier), $this->EncryptionKey);
        $controller = &$this->Controller;

        $confirmationCallbackWrapper = function($raw, $bankConfirmationDetails) use ($config, $remittanceIdentifier, &$controller)
        {
            if ($remittanceIdentifier != $bankConfirmationDetails->GetRemittanceIdentifier())
                throw new eps_bank_transfer\UnknownRemittanceIdentifierException('Remittance identifier mismatch '
                    . $remittanceIdentifier . ' != ' . $bankConfirmationDetails->GetRemittanceIdentifier());

            return call_user_func_array([$controller, $config['ConfirmationCallback']], [$raw, $bankConfirmationDetails]);
        };

        try {
            Plugin::GetSoCommunicator()->HandleConfirmationUrl(
                    $confirmationCallbackWrapper,
                    empty($config['VitalityCheckCallback']) ? null:array($this->Controller, $config['VitalityCheckCallback']),
                    $rawPostStream,
                    $outputStream);
        } catch (Exception $ex) {
            Plugin::WriteLog('Exception in SoCommunicator::HandleConfirmationUrl: ' . $ex->getMessage());
        }

        Plugin::WriteLog('END: Handle confirmation url');
    }

}