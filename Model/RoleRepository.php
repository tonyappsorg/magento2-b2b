<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;
use Tonyapps\B2b\Model\ResourceModel\Role\CollectionFactory;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private readonly RoleResource $resource,
        private readonly RoleFactory $roleFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly Permissions $permissions,
        private readonly CompanyResource $companyResource,
        private readonly SettingResolver $settingResolver
    ) {
    }

    public function save(RoleInterface $role): RoleInterface
    {
        if (trim((string)$role->getRoleName()) === '') {
            throw new CouldNotSaveException(__('Please give the role a name.'));
        }

        if (!$role->getCompanyId()) {
            throw new CouldNotSaveException(__('A role has to belong to a company.'));
        }

        // Unknown permissions are dropped and dependencies added, wherever the role came from.
        $role->setPermissions($this->permissions->normalise($role->getPermissions()));

        try {
            /** @var Role $role */
            $this->resource->save($role);
        } catch (CouldNotSaveException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the role: %1', $exception->getMessage()),
                $exception
            );
        }

        $this->resource->syncDefaultFlag(
            (int)$role->getCompanyId(),
            (int)$role->getId(),
            $role->isDefault()
        );

        return $role;
    }

    public function getById(int $roleId): RoleInterface
    {
        /** @var Role $role */
        $role = $this->roleFactory->create();
        $this->resource->load($role, $roleId);
        if (!$role->getId()) {
            throw new NoSuchEntityException(__('Role with id "%1" does not exist.', $roleId));
        }

        return $role;
    }

    public function delete(RoleInterface $role): bool
    {
        $memberCount = $this->resource->countMembers((int)$role->getId());
        if ($memberCount > 0) {
            throw new CouldNotDeleteException(
                __(
                    '%1 is still held by %2 user(s). Move them to another role first.',
                    $role->getRoleName(),
                    $memberCount
                )
            );
        }

        try {
            /** @var Role $role */
            $this->resource->delete($role);
        } catch (CouldNotDeleteException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the role: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    public function deleteById(int $roleId): bool
    {
        return $this->delete($this->getById($roleId));
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function getByCompanyId(int $companyId): array
    {
        return array_values($this->collectionFactory->create()->addCompanyFilter($companyId)->getItems());
    }

    public function getDefaultForCompany(int $companyId): ?RoleInterface
    {
        $ownerId = $this->getRoleOwnerId($companyId);
        if ($ownerId === null) {
            return null;
        }

        $collection = $this->collectionFactory->create()
            ->addCompanyFilter($ownerId)
            ->addFieldToFilter(RoleInterface::IS_DEFAULT, 1)
            ->setPageSize(1);

        /** @var RoleInterface|null $role */
        $role = $collection->getFirstItem();

        return $role && $role->getId() ? $role : null;
    }

    public function getEffectiveRoles(int $companyId): array
    {
        $ownerId = $this->getRoleOwnerId($companyId);

        return $ownerId === null ? [] : $this->getByCompanyId($ownerId);
    }

    public function isUsableBy(RoleInterface $role, int $companyId): bool
    {
        return $this->settingResolver->isRoleUsable(
            $this->companyResource->getPathById($companyId),
            (int)$role->getCompanyId()
        );
    }

    /**
     * The company whose roles apply here: this one if it defines any, else the nearest ancestor
     * that does.
     */
    private function getRoleOwnerId(int $companyId): ?int
    {
        return $this->settingResolver->resolveRoleOwnerId(
            $this->companyResource->getPathById($companyId)
        );
    }
}
