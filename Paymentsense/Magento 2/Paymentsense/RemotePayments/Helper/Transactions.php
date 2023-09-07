<?php
/*
 * Copyright (C) 2022 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2022 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\RemotePayments\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Transactions Helper
 */
class Transactions extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var Transaction
     */
    protected $_transaction;

    /**
     * @param Context $context
     * @param Transaction $transaction
     */
    public function __construct(
        Context $context,
        Transaction $transaction
    ) {
        $this->_transaction = $transaction;
        parent::__construct($context);
    }

    /**
     * Gets an instance of a Payment Transaction
     *
     * @return Transaction
     */
    protected function getTransaction()
    {
        return $this->_transaction;
    }

    /**
     * Gets payment transaction
     *
     * @param string $fieldValue
     * @param string $fieldName
     *
     * @return Transaction|null
     */
    public function getPaymentTransaction($fieldValue, $fieldName)
    {
        $transaction = null;
        if (!empty($fieldValue)) {
            $transaction = $this->getTransaction()->load($fieldValue, $fieldName);
            if (!$transaction->getId()) {
                $transaction = null;
            }
        }
        return $transaction;
    }

    /**
     * Searches for a transaction by transaction type
     *
     * @param InfoInterface $payment
     * @param string $transactionType
     *
     * @return Transaction|null
     */
    private function lookUpTransaction($payment, $transactionType)
    {
        $lastTransactionId = $payment->getLastTransId();
        $transaction = $this->getPaymentTransaction($lastTransactionId, 'txn_id');
        while (isset($transaction)) {
            if ($transaction->getTxnType() === $transactionType) {
                break;
            }
            $transaction = $this->getPaymentTransaction($transaction->getParentId(), 'transaction_id');
        }
        return $transaction;
    }

    /**
     * Searches for an Authorisation transaction
     *
     * @param InfoInterface $payment
     *
     * @return Transaction|null
     */
    public function lookUpAuthorisationTransaction($payment)
    {
        return $this->lookUpTransaction($payment, TransactionInterface::TYPE_AUTH);
    }

    /**
     * Searches for a Capture transaction
     *
     * @param InfoInterface $payment
     *
     * @return Transaction|null
     */
    public function lookUpCaptureTransaction($payment)
    {
        return $this->lookUpTransaction($payment, TransactionInterface::TYPE_CAPTURE);
    }
}
