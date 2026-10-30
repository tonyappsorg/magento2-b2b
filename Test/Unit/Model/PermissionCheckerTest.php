<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\PermissionChecker;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * The gate every storefront company page goes through.
 */
class PermissionCheckerTest extends TestCase
{
    private CompanyCustomer&MockObject $companyCustomer;
    private RoleRepositoryInterface&MockObject $roleRepository;
    private PermissionChecker $checker;
    private bool $pathActive = true;

    protected function setUp(): void
    {
        $this->companyCustomer = $this->createMock(CompanyCustomer::class);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);
        // Usable unless a test says otherwise; inheritance itself is covered in SettingResolverTest.
        $this->roleRepository->method('isUsableBy')->willReturn(true);

        $this->checker = new PermissionChecker(
            $this->companyCustomer,
            $this->roleRepository,
            new Permissions(),
            $this->companyResource()
        );
    }

    private function company(
        int $status = CompanyInterface::STATUS_ACTIVE,
        ?int $adminCustomerId = 1,
        int $companyId = 10
    ): CompanyInterface&MockObject {
        $this->pathActive = $status === CompanyInterface::STATUS_ACTIVE;
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getStatus')->willReturn($status);
        $company->method('getAdminCustomerId')->willReturn($adminCustomerId);
        $company->method('getId')->willReturn($companyId);

        return $company;
    }

    private function companyResource(): CompanyResource
    {
        $resource = $this->createMock(CompanyResource::class);
        $resource->method('isActivePath')->willReturnCallback(fn(): bool => $this->pathActive);

        return $resource;
    }

    public function testAMemberOfACompanyUnderABlockedParentHasNoPermissions(): void
    {
        $company = $this->company(CompanyInterface::STATUS_ACTIVE, 1);
        $this->pathActive = false; // the company itself is active, its parent is blocked

        self::assertSame([], $this->checker->getPermissions(1, $company));
    }

    private function role(array $permissions, int $companyId = 10): RoleInterface&MockObject
    {
        $role = $this->createMock(RoleInterface::class);
        $role->method('getPermissions')->willReturn($permissions);
        $role->method('getCompanyId')->willReturn($companyId);

        return $role;
    }

    public function testTheCompanyAdministratorMayDoEverything(): void
    {
        self::assertSame(
            (new Permissions())->getAll(),
            $this->checker->getPermissions(1, $this->company())
        );
    }

    public function testACompanyAwaitingApprovalGrantsNothingEvenToItsAdministrator(): void
    {
        self::assertSame(
            [],
            $this->checker->getPermissions(1, $this->company(CompanyInterface::STATUS_PENDING))
        );
    }

    public function testABlockedCompanyGrantsNothing(): void
    {
        self::assertSame(
            [],
            $this->checker->getPermissions(1, $this->company(CompanyInterface::STATUS_BLOCKED))
        );
    }

    public function testAMemberGetsWhatTheirOwnRoleGrants(): void
    {
        $this->companyCustomer->method('getRoleId')->with(7)->willReturn(3);
        $this->roleRepository->method('getById')->with(3)
            ->willReturn($this->role([Permissions::USERS_VIEW]));

        $granted = $this->checker->getPermissions(7, $this->company());

        self::assertContains(Permissions::USERS_VIEW, $granted);
        self::assertNotContains(Permissions::USERS_MANAGE, $granted);
    }

    public function testAMemberAlwaysKeepsTheRightToSeeTheCompanyPage(): void
    {
        $this->companyCustomer->method('getRoleId')->willReturn(3);
        $this->roleRepository->method('getById')->willReturn($this->role([]));

        self::assertSame([Permissions::PROFILE_VIEW], $this->checker->getPermissions(7, $this->company()));
    }

    public function testAMemberWithNoRoleFallsBackToTheCompanyDefault(): void
    {
        $this->companyCustomer->method('getRoleId')->willReturn(null);
        $this->roleRepository->method('getDefaultForCompany')->with(10)
            ->willReturn($this->role([Permissions::ROLES_VIEW]));

        self::assertContains(Permissions::ROLES_VIEW, $this->checker->getPermissions(7, $this->company()));
    }

    public function testWithNoRoleAndNoDefaultAMemberCanOnlySeeTheCompany(): void
    {
        $this->companyCustomer->method('getRoleId')->willReturn(null);
        $this->roleRepository->method('getDefaultForCompany')->willReturn(null);

        self::assertSame([Permissions::PROFILE_VIEW], $this->checker->getPermissions(7, $this->company()));
    }

    public function testARoleTheCompanyCannotUseIsIgnored(): void
    {
        // A stale link to an unrelated company's role must not grant anything; the company
        // default applies instead. What counts as usable is the repository's call.
        $checker = new PermissionChecker(
            $this->companyCustomer,
            $roleRepository = $this->createMock(RoleRepositoryInterface::class),
            new Permissions(),
            $this->companyResource()
        );
        $this->companyCustomer->method('getRoleId')->willReturn(3);
        $roleRepository->method('getById')->willReturn($this->role([Permissions::USERS_MANAGE], 999));
        $roleRepository->method('isUsableBy')->willReturn(false);
        $roleRepository->method('getDefaultForCompany')->willReturn(null);

        self::assertSame([Permissions::PROFILE_VIEW], $checker->getPermissions(7, $this->company()));
    }

    public function testARoleInheritedFromAnAncestorIsAccepted(): void
    {
        // The role row belongs to the parent company, not this one, and that is fine.
        $this->companyCustomer->method('getRoleId')->willReturn(3);
        $this->roleRepository->method('getById')
            ->willReturn($this->role([Permissions::USERS_VIEW], 1));

        self::assertContains(Permissions::USERS_VIEW, $this->checker->getPermissions(7, $this->company()));
    }

    public function testAMissingRoleFallsBackToTheCompanyDefault(): void
    {
        $this->companyCustomer->method('getRoleId')->willReturn(3);
        $this->roleRepository->method('getById')->willThrowException(new NoSuchEntityException());
        $this->roleRepository->method('getDefaultForCompany')
            ->willReturn($this->role([Permissions::USERS_VIEW]));

        self::assertContains(Permissions::USERS_VIEW, $this->checker->getPermissions(7, $this->company()));
    }

    public function testIsAllowedSaysNoWithoutACustomerOrACompany(): void
    {
        self::assertFalse($this->checker->isAllowed(null, $this->company(), Permissions::PROFILE_VIEW));
        self::assertFalse($this->checker->isAllowed(7, null, Permissions::PROFILE_VIEW));
    }

    public function testAnswersAreCachedPerCustomer(): void
    {
        $this->companyCustomer->expects(self::once())->method('getRoleId')->willReturn(null);
        $this->roleRepository->method('getDefaultForCompany')->willReturn(null);

        $this->checker->getPermissions(7, $this->company());
        $this->checker->getPermissions(7, $this->company());
    }

}
