<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Company;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\Adminhtml\Company\AbstractCompany;
use Tonyapps\B2b\Model\Company;
use Tonyapps\B2b\Model\ResourceModel\Company\CollectionFactory;
use Tonyapps\B2b\Model\ResourceModel\CompanyPayment;

/**
 * Data provider for the admin company form.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly CompanyPayment $companyPayment,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        /** @var Company $company */
        foreach ($this->collection->getItems() as $company) {
            $values = $company->getData();
            // The allowed-methods list lives in its own table; the form wants it as a field.
            $values['payment_methods'] = $this->companyPayment->getMethodCodes((int)$company->getId());
            $this->loadedData[(int)$company->getId()] = $values;
        }

        // Values kept from a save that failed validation win over what is in the database.
        $persisted = $this->dataPersistor->get(AbstractCompany::DATA_PERSISTOR_KEY);
        if ($persisted) {
            $companyId = (int)($persisted[CompanyInterface::ENTITY_ID] ?? 0);
            $this->loadedData[$companyId] = $persisted;
            $this->dataPersistor->clear(AbstractCompany::DATA_PERSISTOR_KEY);
        }

        return $this->loadedData;
    }
}
