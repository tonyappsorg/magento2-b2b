<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\Company\CollectionFactory as CompanyCollectionFactory;

/**
 * Child companies, managed by a parent company's own people.
 *
 * A child created here starts Active rather than Pending. Its parent has already been approved,
 * and a storefront user cannot set a customer group — the child inherits the parent's — so a new
 * branch grants nobody anything they did not already have. Making a branch office wait for a
 * second approval would be friction without a safety gain.
 */
class ChildCompanyManagement
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyFactory $companyFactory,
        private readonly CompanyResource $companyResource,
        private readonly CustomerGroupSync $customerGroupSync,
        private readonly CompanyCollectionFactory $companyCollectionFactory
    ) {
    }

    /**
     * Every company below this one, nearest first.
     *
     * @return CompanyInterface[]
     */
    public function getChildren(CompanyInterface $parent): array
    {
        $descendantIds = array_diff(
            $this->companyResource->getSubtreeIds((int)$parent->getId()),
            [(int)$parent->getId()]
        );

        if (!$descendantIds) {
            return [];
        }

        /** @var CompanyInterface[] $children */
        $children = array_values($this->companyCollectionFactory->create()
            ->addFieldToFilter(CompanyInterface::ENTITY_ID, ['in' => array_values($descendantIds)])
            ->getItems());

        usort(
            $children,
            static fn(CompanyInterface $a, CompanyInterface $b): int
                => [$a->getLevel(), $a->getName()] <=> [$b->getLevel(), $b->getName()]
        );

        return $children;
    }

    /**
     * Add a child under a company.
     *
     * @param array<string, mixed> $data raw form input
     * @throws LocalizedException
     */
    public function create(CompanyInterface $parent, array $data): CompanyInterface
    {
        if (!$this->companyResource->isActivePath((int)$parent->getId())) {
            throw new LocalizedException(
                __('Your company has to be active before it can have child companies.')
            );
        }

        $child = $this->companyFactory->create();
        $this->applyData($child, $data);
        $child->setParentId((int)$parent->getId());
        $child->setStatus(CompanyInterface::STATUS_ACTIVE);
        // NULL, so the child follows whatever its parent resolves to.
        $child->setCustomerGroupId(null);
        $child->setAdminCustomerId($parent->getAdminCustomerId());

        $this->companyRepository->save($child);
        $this->customerGroupSync->syncCompanyTree((int)$child->getId());

        return $child;
    }

    /**
     * Change a child's details.
     *
     * @param array<string, mixed> $data raw form input
     * @throws LocalizedException
     */
    public function update(CompanyInterface $parent, int $childId, array $data): CompanyInterface
    {
        $child = $this->getOwnedChild($parent, $childId);
        $this->applyData($child, $data);
        $this->companyRepository->save($child);

        return $child;
    }

    /**
     * A child of this company, refusing anything that is not actually below it.
     *
     * @throws LocalizedException
     */
    public function getOwnedChild(CompanyInterface $parent, int $childId): CompanyInterface
    {
        if ($childId === (int)$parent->getId()) {
            throw new LocalizedException(__('That is your own company, not a child of it.'));
        }

        try {
            $child = $this->companyRepository->getById($childId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('That company no longer exists.'));
        }

        // The parent must be somewhere on the child's path, or this is somebody else's company.
        if (!in_array((int)$parent->getId(), $child->getPathIds(), true)) {
            throw new LocalizedException(__('That company is not part of your company.'));
        }

        return $child;
    }

    /**
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    private function applyData(CompanyInterface $company, array $data): void
    {
        $name = trim((string)($data[CompanyInterface::NAME] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Please enter a company name.'));
        }

        $company->applyCustomerData($data);
    }
}
