<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Store\Model\StoreManagerInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * Adding and removing the users of a company, from the storefront.
 *
 * ponytail: a new user is a real customer account from the moment they are added — there is no
 * "invited, not yet accepted" state to track. They get Magento's own "set a password" email, the
 * same one an administrator-created customer gets. Add an invitation table only if someone needs
 * to see who has not accepted yet.
 *
 * An email that already has an account is refused here: that person never agreed to join, and joining
 * changes their customer group (their prices) and lets the company see their orders. An administrator
 * can still assign an existing customer from the admin.
 */
class CompanyUserManagement
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AccountManagementInterface $accountManagement,
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyCustomer $companyCustomer,
        private readonly StoreManagerInterface $storeManager,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * Everyone in a company.
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface[]
     */
    public function getUsers(int $companyId): array
    {
        $memberIds = $this->companyCustomer->getMemberIds([$companyId]);
        if (!$memberIds) {
            return [];
        }

        // One query for everyone; a customer deleted from under us takes the link row with them via the FK.
        return array_values($this->customerRepository->getList(
            $this->searchCriteriaBuilder->addFilter('entity_id', $memberIds, 'in')->create()
        )->getItems());
    }

    /**
     * Add a user to a company, creating their customer account.
     *
     * @throws LocalizedException
     */
    public function addUser(
        CompanyInterface $company,
        string $email,
        string $firstname,
        string $lastname,
        ?int $roleId = null
    ): int {
        $roleId = $this->validateRole($company, $roleId);

        $email = trim($email);
        $firstname = trim($firstname);
        $lastname = trim($lastname);

        if ($email === '' || $firstname === '' || $lastname === '') {
            throw new LocalizedException(__('Please fill in the name and email address of the new user.'));
        }

        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();

        if ($this->findCustomer($email, $websiteId) !== null) {
            throw new LocalizedException(
                __('%1 already has an account with us. Contact us to have it added to your company.', $email)
            );
        }

        $customer = $this->customerFactory->create();
        $customer->setEmail($email);
        $customer->setFirstname($firstname);
        $customer->setLastname($lastname);
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId((int)$this->storeManager->getStore()->getId());

        try {
            // No password: createAccount sends Magento's own "set a password" email.
            $customer = $this->accountManagement->createAccount($customer);
        } catch (InputMismatchException) {
            throw new LocalizedException(__('An account already exists for %1.', $email));
        }

        $this->membership->assign((int)$customer->getId(), (int)$company->getId(), $roleId);

        return (int)$customer->getId();
    }

    /**
     * Remove a user from a company. Their customer account is left alone.
     *
     * @throws LocalizedException
     */
    public function removeUser(CompanyInterface $company, int $customerId): void
    {
        if ($this->membership->getCompanyId($customerId) !== (int)$company->getId()) {
            throw new LocalizedException(__('That user is not part of your company.'));
        }

        if ($company->getAdminCustomerId() === $customerId) {
            throw new LocalizedException(
                __('You cannot remove the company administrator. Ask us to hand the role to someone else first.')
            );
        }

        $this->membership->assign($customerId, null);
    }

    /**
     * Give an existing member a different role.
     *
     * @throws LocalizedException
     */
    public function setUserRole(CompanyInterface $company, int $customerId, ?int $roleId, ?int $actingCustomerId = null): void
    {
        if ($actingCustomerId !== null && $actingCustomerId === $customerId) {
            // Otherwise anyone allowed to manage users could hand themselves any role the company has.
            throw new LocalizedException(__('You cannot change your own role.'));
        }

        if ($this->membership->getCompanyId($customerId) !== (int)$company->getId()) {
            throw new LocalizedException(__('That user is not part of your company.'));
        }

        if ($company->getAdminCustomerId() === $customerId) {
            throw new LocalizedException(
                __('The company administrator is not limited by a role.')
            );
        }

        $this->membership->setRole($customerId, $this->validateRole($company, $roleId));
    }

    /**
     * The role each member currently holds, keyed by customer ID; absent means the company default.
     *
     * @param int[] $customerIds
     * @return array<int, int>
     */
    public function getRoleIds(array $customerIds): array
    {
        return $this->companyCustomer->getRoleIds(array_map('intval', $customerIds));
    }

    /**
     * Refuse a role belonging to somebody else's company.
     *
     * @throws LocalizedException
     */
    private function validateRole(CompanyInterface $company, ?int $roleId): ?int
    {
        if ($roleId === null) {
            return null;
        }

        try {
            $role = $this->roleRepository->getById($roleId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('That role no longer exists.'));
        }

        if (!$this->roleRepository->isUsableBy($role, (int)$company->getId())) {
            throw new LocalizedException(__('That role does not belong to your company.'));
        }

        return $roleId;
    }

    private function findCustomer(string $email, int $websiteId): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        try {
            return $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
