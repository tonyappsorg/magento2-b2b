<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\Company\CreatePost;
use Tonyapps\B2b\Model\CompanyContext;

/**
 * Backs both company forms on the storefront: registration and profile edit.
 *
 * One block and one template for both, because the fields are the same set — only the action URL
 * and the values they start with differ.
 */
class Form extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether this is the registration form rather than the profile form.
     */
    public function isRegistration(): bool
    {
        return (bool)$this->getData('is_registration');
    }

    public function getFormAction(): string
    {
        return $this->getUrl($this->isRegistration() ? 'b2b/company/createPost' : 'b2b/company/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl($this->isRegistration() ? 'customer/account' : 'b2b/company/index');
    }

    public function getCompany(): ?CompanyInterface
    {
        return $this->companyContext->getCompany();
    }

    /**
     * Current value of a field: what a failed submission left behind, else what is stored.
     */
    public function getFieldValue(string $field): string
    {
        $persisted = $this->getPersistedData();
        if (array_key_exists($field, $persisted)) {
            $value = $persisted[$field];

            return is_array($value) ? implode("\n", $value) : (string)$value;
        }

        if ($this->isRegistration()) {
            return '';
        }

        return (string)($this->getCompany()?->getData($field) ?? '');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        return $this->countryCollectionFactory->create()->loadByStore()->toOptionArray(false);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPersistedData(): array
    {
        if (!$this->isRegistration()) {
            return [];
        }

        $data = $this->dataPersistor->get(CreatePost::DATA_PERSISTOR_KEY);
        $this->dataPersistor->clear(CreatePost::DATA_PERSISTOR_KEY);

        return is_array($data) ? $data : [];
    }
}
