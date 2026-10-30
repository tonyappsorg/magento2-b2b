<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\CompanyPayment;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;
use Tonyapps\B2b\Model\SettingResolver;

class SettingResolverTest extends TestCase
{
    private CompanyResource&MockObject $companyResource;
    private RoleResource&MockObject $roleResource;
    private CompanyPayment&MockObject $companyPayment;
    private SettingResolver $resolver;

    protected function setUp(): void
    {
        $this->companyResource = $this->createMock(CompanyResource::class);
        $this->roleResource = $this->createMock(RoleResource::class);
        $this->companyPayment = $this->createMock(CompanyPayment::class);
        $this->resolver = new SettingResolver(
            $this->companyResource,
            $this->roleResource,
            $this->companyPayment
        );
    }

    public function testCompanyOwnGroupWinsOverItsAncestors(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([1 => 2, 4 => 3, 9 => 7]);

        self::assertSame(7, $this->resolver->resolveCustomerGroupId('1/4/9'));
    }

    public function testNearestAncestorWinsWhenTheCompanyInherits(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([1 => 2, 4 => 3, 9 => null]);

        self::assertSame(3, $this->resolver->resolveCustomerGroupId('1/4/9'));
    }

    public function testFallsBackToTheRootWhenEveryIntermediateInherits(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([1 => 2, 4 => null, 9 => null]);

        self::assertSame(2, $this->resolver->resolveCustomerGroupId('1/4/9'));
    }

    public function testReturnsNullWhenNoCompanyOnThePathSetsAGroup(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([1 => null, 4 => null]);

        self::assertNull($this->resolver->resolveCustomerGroupId('1/4'));
    }

    public function testReturnsNullForAnEmptyPathWithoutQuerying(): void
    {
        $this->companyResource->expects(self::once())->method('getColumnByIds')->with(CompanyInterface::CUSTOMER_GROUP_ID, [])->willReturn([]);

        self::assertNull($this->resolver->resolveCustomerGroupId(null));
    }

    public function testBatchResolvesEveryPathInOneQuery(): void
    {
        // Root 1 (group 2) -> child 4 (own group 3) -> grandchild 9 (inherits 3); sibling 5 inherits 2.
        $this->companyResource->expects(self::once())
            ->method('getColumnByIds')
            ->with(CompanyInterface::CUSTOMER_GROUP_ID, [1, 4, 9, 5])
            ->willReturn([1 => 2, 4 => 3, 9 => null, 5 => null]);

        $resolved = $this->resolver->resolveCustomerGroupIds([
            1 => '1',
            4 => '1/4',
            9 => '1/4/9',
            5 => '1/5',
        ]);

        self::assertSame([1 => 2, 4 => 3, 9 => 3, 5 => 2], $resolved);
    }

    public function testRolesComeFromTheNearestAncestorThatDefinesAny(): void
    {
        // Root 1 and child 4 both define roles; the grandchild defines none, so child 4's win.
        $this->roleResource->method('getCompanyIdsWithRoles')->willReturn([1, 4]);

        self::assertSame(4, $this->resolver->resolveRoleOwnerId('1/4/9'));
    }

    public function testACompanysOwnRolesWinOverItsAncestors(): void
    {
        $this->roleResource->method('getCompanyIdsWithRoles')->willReturn([1, 4, 9]);

        self::assertSame(9, $this->resolver->resolveRoleOwnerId('1/4/9'));
    }

    public function testRolesFallAllTheWayBackToTheRoot(): void
    {
        $this->roleResource->method('getCompanyIdsWithRoles')->willReturn([1]);

        self::assertSame(1, $this->resolver->resolveRoleOwnerId('1/4/9'));
    }

    public function testNoRolesAnywhereOnThePathResolvesToNothing(): void
    {
        $this->roleResource->method('getCompanyIdsWithRoles')->willReturn([]);

        self::assertNull($this->resolver->resolveRoleOwnerId('1/4/9'));
    }

    public function testARoleFromAnyAncestorIsUsable(): void
    {
        self::assertTrue($this->resolver->isRoleUsable('1/4/9', 1));
        self::assertTrue($this->resolver->isRoleUsable('1/4/9', 9));
    }

    public function testARoleFromOutsideTheAncestryIsNotUsable(): void
    {
        // A sibling's role, and a descendant's: neither is on this company's path.
        self::assertFalse($this->resolver->isRoleUsable('1/4/9', 5));
        self::assertFalse($this->resolver->isRoleUsable('1/4', 9));
    }

    public function testPaymentInheritKeepsWalkingUpwards(): void
    {
        // Child and grandchild inherit; the root restricts, so the root's list applies.
        $this->companyResource->method('getColumnByIds')->willReturn([
            1 => CompanyInterface::PAYMENT_MODE_RESTRICT,
            4 => CompanyInterface::PAYMENT_MODE_INHERIT,
            9 => CompanyInterface::PAYMENT_MODE_INHERIT,
        ]);
        $this->companyPayment->expects(self::once())->method('getMethodCodes')->with(1)
            ->willReturn(['checkmo']);

        self::assertSame(['checkmo'], $this->resolver->resolvePaymentMethods('1/4/9'));
    }

    public function testAllowEveryMethodStopsTheWalk(): void
    {
        // The child opts out of the root's restriction, so nothing below it is restricted either.
        $this->companyResource->method('getColumnByIds')->willReturn([
            1 => CompanyInterface::PAYMENT_MODE_RESTRICT,
            4 => CompanyInterface::PAYMENT_MODE_ALL,
            9 => CompanyInterface::PAYMENT_MODE_INHERIT,
        ]);
        $this->companyPayment->expects(self::never())->method('getMethodCodes');

        self::assertNull($this->resolver->resolvePaymentMethods('1/4/9'));
    }

    public function testACompanysOwnRestrictionWinsOverItsAncestors(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([
            1 => CompanyInterface::PAYMENT_MODE_RESTRICT,
            9 => CompanyInterface::PAYMENT_MODE_RESTRICT,
        ]);
        $this->companyPayment->expects(self::once())->method('getMethodCodes')->with(9)
            ->willReturn(['purchaseorder']);

        self::assertSame(['purchaseorder'], $this->resolver->resolvePaymentMethods('1/9'));
    }

    public function testNobodyDecidingMeansNoRestriction(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([
            1 => CompanyInterface::PAYMENT_MODE_INHERIT,
            9 => CompanyInterface::PAYMENT_MODE_INHERIT,
        ]);

        self::assertNull($this->resolver->resolvePaymentMethods('1/9'));
    }

    public function testRestrictingToAnEmptyListIsKeptAsAnEmptyList(): void
    {
        // Distinct from NULL: this company may use nothing, rather than anything.
        $this->companyResource->method('getColumnByIds')
            ->willReturn([1 => CompanyInterface::PAYMENT_MODE_RESTRICT]);
        $this->companyPayment->method('getMethodCodes')->willReturn([]);

        self::assertSame([], $this->resolver->resolvePaymentMethods('1'));
    }

    public function testBatchIgnoresGarbageSegments(): void
    {
        $this->companyResource->method('getColumnByIds')->willReturn([4 => 6]);

        self::assertSame([0 => 6], $this->resolver->resolveCustomerGroupIds(['0//4']));
    }
}
