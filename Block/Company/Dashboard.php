<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\Source\Status;

/**
 * Company dashboard summary.
 */
class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly Status $statusSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCompany(): ?CompanyInterface
    {
        return $this->companyContext->getCompany();
    }

    public function canEditProfile(): bool
    {
        return $this->companyContext->isAllowed(Permissions::PROFILE_EDIT);
    }

    public function canViewUsers(): bool
    {
        return $this->companyContext->isAllowed(Permissions::USERS_VIEW);
    }

    public function canViewRoles(): bool
    {
        return $this->companyContext->isAllowed(Permissions::ROLES_VIEW);
    }

    public function getRolesUrl(): string
    {
        return $this->getUrl('b2b/role/index');
    }

    public function canViewChildren(): bool
    {
        return $this->companyContext->isAllowed(Permissions::CHILDREN_VIEW);
    }

    public function canViewCompanyOrders(): bool
    {
        return $this->companyContext->isAllowed(Permissions::ORDERS_VIEW_COMPANY);
    }

    public function getChildrenUrl(): string
    {
        return $this->getUrl('b2b/child/index');
    }

    public function getCompanyOrdersUrl(): string
    {
        return $this->getUrl('b2b/order/index');
    }

    public function canViewCredit(): bool
    {
        return $this->companyContext->isAllowed(Permissions::CREDIT_VIEW);
    }

    public function getCreditUrl(): string
    {
        return $this->getUrl('b2b/credit/index');
    }

    public function isPending(): bool
    {
        return $this->getCompany()?->getStatus() === CompanyInterface::STATUS_PENDING;
    }

    public function getStatusLabel(): string
    {
        $status = $this->getCompany()?->getStatus();
        $labels = array_column($this->statusSource->toOptionArray(), 'label', 'value');

        return (string)($labels[$status] ?? '');
    }

    /**
     * Address as display lines, skipping the parts the company has not filled in.
     *
     * @return string[]
     */
    public function getAddressLines(): array
    {
        $company = $this->getCompany();
        if ($company === null) {
            return [];
        }

        $lines = array_filter(explode("\n", (string)$company->getData(CompanyInterface::STREET)));
        $cityLine = implode(
            ', ',
            array_filter([
                (string)$company->getData(CompanyInterface::CITY),
                (string)$company->getData(CompanyInterface::REGION),
                (string)$company->getData(CompanyInterface::POSTCODE),
            ])
        );
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        $country = (string)$company->getData(CompanyInterface::COUNTRY_ID);
        if ($country !== '') {
            $lines[] = $country;
        }

        return array_values($lines);
    }

    public function getEditUrl(): string
    {
        return $this->getUrl('b2b/company/edit');
    }

    public function getUsersUrl(): string
    {
        return $this->getUrl('b2b/user/index');
    }
}
