<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Role;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Controller\Adminhtml\Role\AbstractRole;
use Tonyapps\B2b\Model\ResourceModel\Role\CollectionFactory;
use Tonyapps\B2b\Model\Role;

/**
 * Data provider for the admin role form.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param RequestInterface $request
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RequestInterface $request,
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
        /** @var Role $role */
        foreach ($this->collection->getItems() as $role) {
            $values = $role->getData();
            // The form's checkbox set wants a list, not the stored comma-separated string.
            $values[RoleInterface::PERMISSIONS] = $role->getPermissions();
            $this->loadedData[(int)$role->getId()] = $values;
        }

        if (!$this->loadedData) {
            // Adding a role: carry the company through from the URL so the form can post it.
            $companyId = (int)$this->request->getParam(RoleInterface::COMPANY_ID);
            $this->loadedData[0] = [RoleInterface::COMPANY_ID => $companyId ?: null];
        }

        $persisted = $this->dataPersistor->get(AbstractRole::DATA_PERSISTOR_KEY);
        if ($persisted) {
            $this->loadedData[(int)($persisted[RoleInterface::ROLE_ID] ?? 0)] = $persisted;
            $this->dataPersistor->clear(AbstractRole::DATA_PERSISTOR_KEY);
        }

        return $this->loadedData;
    }
}
