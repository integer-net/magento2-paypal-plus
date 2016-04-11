<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Author Robert Hillebrand - hillebrand@i-ways.de - i-ways sales solutions GmbH
 * Copyright i-ways sales solutions GmbH © 2015. All Rights Reserved.
 * License http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

namespace Iways\PayPalPlus\Model;

use Magento\Payment\Model\Method\Free;

class MethodList extends \Magento\Payment\Model\MethodList
{
    /**
     * @param \Magento\Payment\Helper\Data $paymentHelper
     * @param \Magento\Payment\Model\Checks\SpecificationFactory $specificationFactory
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Payment\Model\Checks\SpecificationFactory $specificationFactory
    ) {
        parent::__construct($paymentHelper, $specificationFactory);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param boolean $checkPPP
     * @return \Magento\Payment\Model\MethodInterface[]
     * @api
     */
    public function getAvailableMethods(\Magento\Quote\Api\Data\CartInterface $quote = null, $checkPPP = true)
    {
        $store = $quote ? $quote->getStoreId() : null;
        $methods = [];
        $isFreeAdded = false;
        foreach ($this->paymentHelper->getStoreMethods($store, $quote) as $method) {
            if ($this->_canUseMethod($method, $quote)) {
                $method->setInfoInstance($quote->getPayment());
                if($checkPPP && $method->getCode() == Payment::CODE) {
                    return [$method];
                }
                $methods[] = $method;
                if ($method->getCode() == Free::PAYMENT_METHOD_FREE_CODE) {
                    $isFreeAdded = true;
                }
            }
        }
        if (!$isFreeAdded && !$quote->getGrandTotal()) {
            /** @var \Magento\Payment\Model\Method\Free $freeMethod */
            $freeMethod = $this->paymentHelper->getMethodInstance(Free::PAYMENT_METHOD_FREE_CODE);
            if ($freeMethod->isAvailableInConfig()) {
                $freeMethod->setInfoInstance($quote->getPayment());
                $methods[] = $freeMethod;
            }
        }
        return $methods;
    }
}