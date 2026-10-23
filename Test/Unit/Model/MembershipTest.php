<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CustomerGroupSync;
use Tonyapps\B2b\Model\Membership;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

class MembershipTest extends TestCase
{
    private CompanyCustomer&MockObject $companyCustomer;
    private CompanyRepositoryInterface&MockObject $companyRepository;
    private CustomerGroupSync&MockObject $customerGroupSync;
    private Membership $membership;

    protected function setUp(): void
    {
        $this->companyCustomer = $this->createMock(CompanyCustomer::class);
        $this->companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $this->customerGroupSync = $this->createMock(CustomerGroupSync::class);

        $this->membership = new Membership(
            $this->companyCustomer,
            $this->companyRepository,
            $this->customerGroupSync
        );
    }

    public function testAssigningWritesTheLinkAndAppliesTheGroup(): void
    {
        $this->companyCustomer->method('getCompanyId')->willReturn(null);
        $this->companyRepository->expects(self::once())
            ->method('getById')
            ->with(42)
            ->willReturn($this->createMock(CompanyInterface::class));
        $this->companyCustomer->expects(self::once())->method('assign')->with(7, 42);
        $this->customerGroupSync->expects(self::once())->method('syncCustomer')->with(7, 42);

        $this->membership->assign(7, 42);
    }

    public function testAssigningToAMissingCompanyWritesNothing(): void
    {
        $this->companyCustomer->method('getCompanyId')->willReturn(null);
        $this->companyRepository->method('getById')->willThrowException(new NoSuchEntityException());
        $this->companyCustomer->expects(self::never())->method('assign');
        $this->customerGroupSync->expects(self::never())->method('syncCustomer');

        $this->expectException(NoSuchEntityException::class);
        $this->membership->assign(7, 42);
    }

    public function testRemovingFromACompanyDropsTheLinkAndResetsTheGroup(): void
    {
        $this->companyCustomer->method('getCompanyId')->with(7)->willReturn(42);
        $this->companyCustomer->expects(self::once())->method('unassign')->with(7);
        $this->customerGroupSync->expects(self::once())->method('syncCustomer')->with(7, null);

        $this->membership->assign(7, null);
    }

    public function testReassigningToTheSameCompanyIsANoOp(): void
    {
        $this->companyCustomer->method('getCompanyId')->with(7)->willReturn(42);
        $this->companyCustomer->expects(self::never())->method('assign');
        $this->companyCustomer->expects(self::never())->method('unassign');
        $this->customerGroupSync->expects(self::never())->method('syncCustomer');

        $this->membership->assign(7, 42);
    }

    public function testRemovingACustomerWhoHasNoCompanyIsANoOp(): void
    {
        $this->companyCustomer->method('getCompanyId')->willReturn(null);
        $this->companyCustomer->expects(self::never())->method('unassign');
        $this->customerGroupSync->expects(self::never())->method('syncCustomer');

        $this->membership->assign(7, null);
    }
}
