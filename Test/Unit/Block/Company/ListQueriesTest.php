<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Block\Company;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Block\Company\Orders;
use Tonyapps\B2b\Block\Company\Roles;
use Tonyapps\B2b\Block\Company\Users;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\CompanyUserManagement;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;

/**
 * The company list pages: a fixed number of queries per render, not one per row or per company.
 */
class ListQueriesTest extends TestCase
{
    private ObjectManager $objectManager;
    private CompanyContext $companyContext;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(4);
        $this->companyContext = $this->createMock(CompanyContext::class);
        $this->companyContext->method('getCompany')->willReturn($company);
    }

    public function testOrdersLoadOnlyTheSubtreesCompanyNames(): void
    {
        $companyResource = $this->createMock(CompanyResource::class);
        $companyResource->expects(self::once())->method('getSubtreeIds')->with(4)->willReturn([4, 9]);
        $companyResource->expects(self::once())->method('getCompanyOptions')->with(null, [4, 9])
            ->willReturn([4 => 'Acme', 9 => 'Acme East']);

        $block = $this->objectManager->getObject(Orders::class, [
            'companyContext' => $this->companyContext,
            'companyResource' => $companyResource,
        ]);

        self::assertSame([4 => 'Acme', 9 => 'Acme East'], $block->getCompanyNames());
    }

    public function testRoleMemberCountsAreFetchedOnceForTheWholeList(): void
    {
        $roles = [];
        foreach ([3, 5] as $roleId) {
            $role = $this->createMock(RoleInterface::class);
            $role->method('getId')->willReturn($roleId);
            $roles[] = $role;
        }
        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $roleRepository->expects(self::once())->method('getByCompanyId')->with(4)->willReturn($roles);
        $roleResource = $this->createMock(RoleResource::class);
        $roleResource->expects(self::never())->method('countMembers');
        $roleResource->expects(self::once())->method('countMembersByRole')->with([3, 5])->willReturn([3 => 2]);

        $block = $this->objectManager->getObject(Roles::class, [
            'companyContext' => $this->companyContext,
            'roleRepository' => $roleRepository,
            'roleResource' => $roleResource,
        ]);

        self::assertSame(2, $block->getMemberCount($roles[0]));
        self::assertSame(0, $block->getMemberCount($roles[1]));
    }

    public function testUsersAreLoadedOncePerRender(): void
    {
        $user = $this->createMock(CustomerInterface::class);
        $user->method('getId')->willReturn(7);
        $userManagement = $this->createMock(CompanyUserManagement::class);
        $userManagement->expects(self::once())->method('getUsers')->with(4)->willReturn([$user]);
        $companyCustomer = $this->createMock(CompanyCustomer::class);
        $companyCustomer->method('getRoleIds')->willReturn([7 => 3]);

        $block = $this->objectManager->getObject(Users::class, [
            'companyContext' => $this->companyContext,
            'userManagement' => $userManagement,
            'companyCustomer' => $companyCustomer,
        ]);

        self::assertSame([$user], $block->getUsers());
        self::assertSame(3, $block->getRoleId($user));
    }
}
