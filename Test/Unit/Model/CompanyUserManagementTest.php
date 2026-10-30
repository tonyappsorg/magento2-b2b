<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\CompanyUserManagement;
use Tonyapps\B2b\Model\PermissionChecker;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * A member managing users may hand out only what they hold themselves.
 */
class CompanyUserManagementTest extends TestCase
{
    private MembershipManagementInterface&MockObject $membership;
    private RoleRepositoryInterface&MockObject $roleRepository;
    private PermissionChecker&MockObject $permissionChecker;
    private AccountManagementInterface&MockObject $accountManagement;
    private CompanyUserManagement $management;
    private CompanyInterface&MockObject $company;

    protected function setUp(): void
    {
        $this->membership = $this->createMock(MembershipManagementInterface::class);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $this->roleRepository->method('isUsableBy')->willReturn(true);
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
        // The acting member (7) holds users_manage and users_view only.
        $this->permissionChecker->method('canGrant')->willReturnCallback(
            static fn(int $customerId, CompanyInterface $company, array $permissions): bool
                => !array_diff($permissions, [Permissions::USERS_VIEW, Permissions::USERS_MANAGE])
        );
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('get')->willThrowException(new NoSuchEntityException());
        $customerFactory = $this->createMock(CustomerInterfaceFactory::class);
        $customerFactory->method('create')->willReturn($this->createMock(CustomerInterface::class));
        $store = $this->createMock(StoreInterface::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->management = new CompanyUserManagement(
            $customerRepository,
            $customerFactory,
            $this->accountManagement,
            $this->membership,
            $this->createMock(CompanyCustomer::class),
            $storeManager,
            $this->roleRepository,
            $this->createMock(SearchCriteriaBuilder::class),
            $this->permissionChecker
        );

        $this->company = $this->createMock(CompanyInterface::class);
        $this->company->method('getId')->willReturn(10);
        $this->company->method('getAdminCustomerId')->willReturn(1);
    }

    private function role(array $permissions): RoleInterface
    {
        $role = $this->createMock(RoleInterface::class);
        $role->method('getPermissions')->willReturn($permissions);

        return $role;
    }

    public function testAddingAUserWithAStrongerRoleIsRefused(): void
    {
        $this->roleRepository->method('getById')->with(3)->willReturn($this->role([Permissions::ROLES_MANAGE]));
        $this->accountManagement->expects(self::never())->method('createAccount');

        $this->expectException(LocalizedException::class);
        $this->management->addUser($this->company, 'me2@example.com', 'A', 'B', 3, 7);
    }

    public function testAddingAUserOnAStrongerCompanyDefaultIsRefused(): void
    {
        $this->roleRepository->method('getDefaultForCompany')->with(10)
            ->willReturn($this->role([Permissions::CREDIT_USE]));
        $this->accountManagement->expects(self::never())->method('createAccount');

        $this->expectException(LocalizedException::class);
        $this->management->addUser($this->company, 'me2@example.com', 'A', 'B', null, 7);
    }

    public function testAddingAUserWithARoleWithinTheActorsOwnRightsWorks(): void
    {
        $this->roleRepository->method('getById')->willReturn($this->role([Permissions::USERS_VIEW]));
        $created = $this->createMock(CustomerInterface::class);
        $created->method('getId')->willReturn(50);
        $this->accountManagement->method('createAccount')->willReturn($created);
        $this->membership->expects(self::once())->method('assign')->with(50, 10, 3);

        self::assertSame(50, $this->management->addUser($this->company, 'new@example.com', 'A', 'B', 3, 7));
    }

    public function testGivingAMemberAStrongerRoleIsRefused(): void
    {
        $this->membership->method('getCompanyId')->willReturn(10);
        $this->roleRepository->method('getById')->willReturn($this->role([Permissions::CHILDREN_MANAGE]));
        $this->membership->expects(self::never())->method('setRole');

        $this->expectException(LocalizedException::class);
        $this->management->setUserRole($this->company, 20, 3, 7);
    }
}
