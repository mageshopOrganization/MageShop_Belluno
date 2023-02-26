<?php

class MageShop_Belluno_Model_Payment_Creditcard_Transaction
{

    /**
     * Function authorize of method payment
     * @param Varien_Object $payment
     * @param $amount
     * @param $info
     */
    public function transactionCreditCard(Varien_Object $payment, $amount, $info)
    {
        $createRequest = $this->getCreateRequest();
        $data = json_decode($payment->getAdditionalInformation("data"), true);
        $request = $createRequest->createRequest($data, $info);

        $connector = $this->getConnector();

        $response = $connector->doRequest($request, "POST", "/transaction");
        $response = json_decode($response, true);

        $info->setAdditionalInformation("transaction_id", $response['transaction']['transaction_id']);
        $info->setAdditionalInformation("value", $response['transaction']['value']);
        $info->setAdditionalInformation("status", $response['transaction']['status']);
        $info->setAdditionalInformation("resBelluno", $connector->getMessege());
    }

    /**Function to return class connector for requests */
    public function getConnector()
    {
        return new MageShop_Belluno_Service_ApiBelluno();
    }

    /**Function to return class create request */
    public function getCreateRequest()
    {
        return new MageShop_Belluno_Model_Payment_Creditcard_CreateRequest();
    }
}
