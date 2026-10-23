<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ChildCompanyManagement;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\Source\Status;

/**
 * The company's child companies, and the form for adding or editing one.
 */
class Children extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly ChildCompanyManagement $childCompanies,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly Status $statusSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCompany(): ?CompanyInterface
    {
        return $this->companyContext->getCompany();
    }

    /**
     * @return CompanyInterface[]
     */
    public function getChildren(): array
    {
        $company = $this->getCompany();

        return $company === null ? [] : $this->childCompanies->getChildren($company);
    }

    public function canManage(): bool
    {
        return $this->companyContext->isAllowed(Permissions::CHILDREN_MANAGE);
    }

    /**
     * How deep below the viewer's own company a child sits, for indenting the list.
     */
    public function getDepth(CompanyInterface $child): int
    {
        $company = $this->getCompany();

        return $company === null ? 0 : max(0, $child->getLevel() - $company->getLevel() - 1);
    }

    public function getStatusLabel(CompanyInterface $child): string
    {
        foreach ($this->statusSource->toOptionArray() as $option) {
            if ($option['value'] === $child->getStatus()) {
                return (string)$option['label'];
            }
        }

        return '';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        $options = [];
        foreach ($this->countryCollectionFactory->create()->loadByStore()->toOptionArray(false) as $country) {
            $options[] = ['value' => (string)$country['value'], 'label' => (string)$country['label']];
        }

        return $options;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('b2b/child/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/company/index');
    }

    /**
     * Children as JSON so the edit buttons can fill the form without a round trip.
     */
    public function getChildrenJson(): string
    {
        $fields = ['name', 'legal_name', 'vat_tax_id', 'email', 'telephone',
                   'street', 'city', 'region', 'postcode', 'country_id'];

        $children = [];
        foreach ($this->getChildren() as $child) {
            $row = ['entity_id' => (int)$child->getId()];
            foreach ($fields as $field) {
                $row[$field] = (string)($child->getData($field) ?? '');
            }
            $children[(int)$child->getId()] = $row;
        }

        return json_encode($children, JSON_THROW_ON_ERROR);
    }
}
