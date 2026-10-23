<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reader for the tonyapps_b2b configuration section.
 */
class Config
{
    private const XML_PATH_ENABLED = 'tonyapps_b2b/general/enabled';
    private const XML_PATH_REGISTRATION_ENABLED = 'tonyapps_b2b/registration/frontend_enabled';
    private const XML_PATH_IN_CUSTOMER_REGISTRATION = 'tonyapps_b2b/registration/in_customer_registration';
    private const XML_PATH_NEW_COMPANY_TEMPLATE = 'tonyapps_b2b/email/new_company_template';
    private const XML_PATH_APPROVED_TEMPLATE = 'tonyapps_b2b/email/approved_template';
    private const XML_PATH_REJECTED_TEMPLATE = 'tonyapps_b2b/email/rejected_template';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Whether a visitor may register a company from the storefront at all.
     */
    public function isRegistrationEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->flag(self::XML_PATH_REGISTRATION_ENABLED, $storeId);
    }

    /**
     * Whether the Create an Account form offers the optional company section.
     */
    public function isOfferedDuringCustomerRegistration(?int $storeId = null): bool
    {
        return $this->isRegistrationEnabled($storeId)
            && $this->flag(self::XML_PATH_IN_CUSTOMER_REGISTRATION, $storeId);
    }

    public function getNewCompanyTemplate(?int $storeId = null): string
    {
        return $this->value(self::XML_PATH_NEW_COMPANY_TEMPLATE, $storeId);
    }

    public function getApprovedTemplate(?int $storeId = null): string
    {
        return $this->value(self::XML_PATH_APPROVED_TEMPLATE, $storeId);
    }

    public function getRejectedTemplate(?int $storeId = null): string
    {
        return $this->value(self::XML_PATH_REJECTED_TEMPLATE, $storeId);
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function value(string $path, ?int $storeId): string
    {
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
