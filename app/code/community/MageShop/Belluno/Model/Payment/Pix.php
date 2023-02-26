<?php
class MageShop_Belluno_Model_Payment_Pix extends Mage_Payment_Model_Method_Abstract
{
  
    protected $_code = 'belluno_pix';
    protected $_formBlockType = 'belluno/form_pix';
    //protected $_infoBlockType = 'mageshop/belluno/info';

    protected $_canOrder = true;
    protected $_isInitializeNeeded = true;
    protected $_isGateway = true;
    protected $_allowCurrencyCode = ["BRL"];


    /**
     * Method that will be executed instead of magento's authorize default
     * workflow
     *
     * @param string $paymentAction
     * @param Varien_Object $stateObject
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $this->authorize($payment, $payment->getOrder()->getBaseTotalDue());
    }


    /**
     * Processa o pedido
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
      if ($this->canOrder()) {

        $info = $this->getInfoInstance();
        $transaction = new MageShop_Belluno_Model_Payment_Pix_Transaction();
        $transaction->transactionPix($payment, $amount, $info); // Processar a transação do PIX

        return $this;
      }
    }

     /**
     *  Essa função é responsável por armazenar as informações do pagamento recebidas do formulário de checkout.
     *  Ela recebe um objeto Varien contendo os dados do formulário e os atribui à instância de informação do pagamento.
     *  Em seguida, realiza uma validação dos dados e armazena-os em "additional_information" no formato de array.
     *
     * @param Varien_Object $data
     * @return void
     */
    public function assignData(Varien_Object $data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCheckNo($data->getCheckNo())->setCheckDate($data->getCheckDate());

        $this->validateAssignData($data);
        $dataAssign = $this->saveAssignData($data);
        $info->setAdditionalInformation("data", $dataAssign);

        return $this;
    }


    /**
     * Function to save assign data
     */
    public function saveAssignData($data)
    {
        $array = [
        'method' => $data['method'],
        'client_document' => $data['client_document']
        ];
        return json_encode($array);
    }
   
    /**
     * Function to validate assign data
     * @var array|object $data
     */
    public function validateAssignData($data)
    {
        $quote = $this->getQuote();
        $customerId = $quote->getCustomerId();
        $billingAddress = $quote->getBillingAddress();

        //client
        $clientName = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        if(strlen($clientName) > 100){
        $clientName = $quote->getCustomerFirstname();
        }

        $taxDocument = $this->getUseTaxDocumentCapture();
        if ($taxDocument == true) {
        $clientDocument = $data['client_document'];
        } else {
        $clientDocument = $this->getTaxVat($customerId);
        }
        if (empty($clientDocument)) {
        $clientDocument = $quote->getCustomerTaxvat();
        }

        $clientEmail = $quote->getCustomerEmail();
        $clientPhone = $billingAddress->getTelephone();
        //billing
        $postalCode = $billingAddress->getPostcode();
        $postalCode = preg_replace('/[^0-9]/is', '', $postalCode);
        $postalCode = substr_replace($postalCode, '-', 5, 0);
        $district = $billingAddress->getRegion();
        $address = $billingAddress->getStreet(1);
        $number = $billingAddress->getStreet(2);
        $city = $billingAddress->getCity();
        $state = $this->getRegionCodeAPI()->getRegionCode($billingAddress->getRegion());

        $this->validationRequest(
            $postalCode,
            $district,
            $address,
            $number,
            $city,
            $state,
            $clientName,
            $clientDocument,
            $clientEmail,
            $clientPhone
        );
    }

    /**
     * Function to validate request
     * @param string $postalCode
     * @param string $district
     * @param string $address
     * @param string $number
     * @param string $city
     * @param string $state
     * @param string $clientName
     * @param string $clientDocument
     * @param string $clientEmail
     * @param string $clientPhone
     */
    public function validationRequest(
        $postalCode,
        $district,
        $address,
        $number,
        $city,
        $state,
        $clientName,
        $clientDocument,
        $clientEmail,
        $clientPhone
    ) {
        $documentValidator = $this->getDocumentsValidator();
        $credentialsValidator = $this->getCredentialsValidator();

        $isValid = $documentValidator->validateDocument($clientDocument);
        if ($isValid == false) {
        Mage::throwException(__('Documento do cliente inválido. Verifique por favor.'));
        }
        $isValid = $credentialsValidator->validateCellphone($clientPhone);
        if ($isValid == false) {
        Mage::throwException(__('Celular do cliente inválido. Verifique por favor.'));
        }
        $credentialsValidator->validateBilling($postalCode, $address, $number, $city, $state, $district);
        $credentialsValidator->validateClientData($clientName, $clientEmail, $clientPhone);
    }

    /**
     * Function to get tax document
     */
    public function getUseTaxDocumentCapture()
    {
        return Mage::getStoreConfig('payment/belluno_pixpayment/capture_tax');
    }

    /**
     * Function to get TaxVat
     * @param $customerId
     */
    public function getTaxVat($customerId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        $vatNumber = $customer->getData('taxvat');
        return $vatNumber;
    }

    /** 
     * Get checkout session 
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**Function to return class region code API */
    public function getRegionCodeAPI()
    {
        return new MageShop_Belluno_Validations_RegionCodeAPI();
    }

    /**Function to return class credentials validator */
    public function getCredentialsValidator()
    {
        return new MageShop_Belluno_Validations_CredentialsValidator();
    }

    /**Function to return class documents validator*/
    public function getDocumentsValidator()
    {
        return new MageShop_Belluno_Validations_DocumentsValidator();
    }
    

}
