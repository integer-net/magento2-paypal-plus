<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 7.3.17
 *
 * @category Modules
 * @package  Magento
 * @author   Robert Hillebrand <hillebrand@i-ways.net>
 * @license  http://opensource.org/licenses/osl-3.0.php Open Software License 3.0
 * @link     https://www.i-ways.net
 */

namespace Iways\PayPalPlus\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Protected $methodCode
     *
     * @var string[]
     */
    protected $methodCode = Payment::CODE;

    /**
     * Protected $method
     *
     * @var Checkmo
     */
    protected $method;

    /**
     * Protected $escaper
     *
     * @var Escaper
     */
    protected $escaper;

    /**
     * Protected $payPalPlusHelper
     *
     * @var \Iways\PayPalPlus\Helper\Data
     */
    protected $payPalPlusHelper;

    /**
     * Protected $checkoutSession
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * Protected $scopeConfig
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Protected $paymentConfig
     *
     * @var \Magento\Payment\Model\Config
     */
    protected $paymentConfig;

    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Protected $methodList
     *
     * @var MethodList
     */
    protected $methodList;

    /**
     * Protected $logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Protected $assetRepo
     *
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $assetRepo;

    /**
     * Protected $request
     *
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * ConfigProvider constructor
     *
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     * @param \Iways\PayPalPlus\Helper\Data $payPalPlusHelper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param MethodList $methodList
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     * @param Magento\Framework\View\Asset\Repository $assetRepo
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        \Iways\PayPalPlus\Helper\Data $payPalPlusHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Payment\Model\Config $paymentConfig,
        MethodList $methodList,
        \Magento\Framework\UrlInterface $urlBuilder,
        LoggerInterface $logger,
        Repository $assetRepo,
        \Magento\Framework\App\Request\Http $request
    ) {
        $this->escaper = $escaper;
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->payPalPlusHelper = $payPalPlusHelper;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
        $this->paymentConfig = $paymentConfig;
        $this->methodList = $methodList;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        if ($this->request->getFullActionName() == 'checkout_cart_index') {
            return [];
        }

        $showPuiOnSandbox = $this->scopeConfig->getValue(
            'iways_paypalplus/dev/pui_sandbox',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ? true : false;

        $showLoadingIndicator = $this->scopeConfig->getValue(
            'payment/iways_paypalplus_payment/show_loading_indicator',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ? true : false;

        $mode = $this->scopeConfig->getValue(
            'iways_paypalplus/api/mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $language = $this->scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $this->method->isAvailable() ? [
            'payment' => [
                'iways_paypalplus_payment' => [
                    'paymentExperience' => $this->payPalPlusHelper->getPaymentExperience(),
                    'showPuiOnSandbox' => $showPuiOnSandbox,
                    'showLoadingIndicator' => $showLoadingIndicator,
                    'mode' => $mode,
                    'country' => $this->getCountry(),
                    'language' => $language,
                    'thirdPartyPaymentMethods' => $this->getThirdPartyMethods()
                ],
            ],
        ] : [];
    }

    protected function getCountry()
    {
        $billingAddress = $this->checkoutSession->getQuote()->getBillingAddress();
        if ($billingAddress->getCountryId()) {
            return $billingAddress->getCountryId();
        }

        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        if ($shippingAddress->getCountryId()) {
            return $shippingAddress->getCountryId();
        }

        return $this->scopeConfig->getValue(
            'paypal/general/merchant_country',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    protected function getThirdPartyMethods()
    {
        $this->methodList->setCheckPPP(true);
        $paymentMethods = $this->methodList->getAvailableMethods($this->checkoutSession->getQuote());
        $this->methodList->setCheckPPP(false);
        $allowedPPPMethods = explode(
            ',',
            (string)$this->scopeConfig->getValue(
                'payment/iways_paypalplus_payment/third_party_moduls',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        );
        $methods = [];
        foreach ($paymentMethods as $paymentMethod) {
            if (strpos($paymentMethod->getCode(), 'paypal') === false
                && in_array($paymentMethod->getCode(), $allowedPPPMethods)
            ) {
                if ($methodImage = $this->scopeConfig->getValue(
                    'payment/iways_paypalplus_section/third_party_modul_info_image_' . $paymentMethod->getCode(),
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )
                ) {
                    if (substr($methodImage, 0, 4) != 'http') {
                        $methodImage = $this->assetRepo->getUrl($methodImage);
                    }
                }

                $method = [
                    'redirectUrl' => $this->urlBuilder->getUrl('checkout', ['_secure' => true]),
                    'methodName' => $paymentMethod->getTitle(),
                    'imageUrl' => $methodImage,
                    'description' => $this->scopeConfig->getValue(
                        'payment/iways_paypalplus_section/third_party_modul_info_text_' . $paymentMethod->getCode(),
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                ];
                $methods[$paymentMethod->getCode()] = $method;
            }
        }
        return $methods;
    }
}
