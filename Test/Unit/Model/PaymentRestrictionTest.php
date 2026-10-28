<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\Config;
use Tonyapps\B2b\Model\PaymentRestriction;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\SettingResolver;

class PaymentRestrictionTest extends TestCase
{
    private MembershipManagementInterface&MockObject $membership;
    private CompanyRepositoryInterface&MockObject $companyRepository;
    private SettingResolver&MockObject $settingResolver;
    private Config&MockObject $config;
    private PaymentRestriction $restriction;
    private bool $pathActive = true;

    protected function setUp(): void
    {
        $this->membership = $this->createMock(MembershipManagementInterface::class);
        $this->companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $this->settingResolver = $this->createMock(SettingResolver::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(true);

        $this->restriction = new PaymentRestriction(
            $this->membership,
            $this->companyRepository,
            $this->companyResource(),
            $this->settingResolver,
            $this->config
        );
    }

    private function inCompany(int $status = CompanyInterface::STATUS_ACTIVE): void
    {
        $this->pathActive = $status === CompanyInterface::STATUS_ACTIVE;
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getStatus')->willReturn($status);
        $company->method('getId')->willReturn(10);
        $this->membership->method('getCompanyId')->willReturn(10);
        $this->companyRepository->method('getById')->willReturn($company);
    }

    private function companyResource(): CompanyResource
    {
        $resource = $this->createMock(CompanyResource::class);
        $resource->method('isActivePath')->willReturnCallback(fn(): bool => $this->pathActive);
        $resource->method('getPathById')->willReturn('10');

        return $resource;
    }

    public function testACompanyUnderABlockedParentIsNotRestricted(): void
    {
        $this->inCompany();
        $this->pathActive = false;

        self::assertNull($this->restriction->getAllowedMethods(7));
    }

    public function testAGuestIsNotRestricted(): void
    {
        self::assertNull($this->restriction->getAllowedMethods(null));
        self::assertTrue($this->restriction->isMethodAllowed(null, 'checkmo'));
    }

    public function testACustomerWithNoCompanyIsNotRestricted(): void
    {
        $this->membership->method('getCompanyId')->willReturn(null);

        self::assertNull($this->restriction->getAllowedMethods(7));
    }

    public function testOnlyTheListedMethodsAreAllowed(): void
    {
        $this->inCompany();
        $this->settingResolver->method('resolvePaymentMethods')->willReturn(['purchaseorder', 'checkmo']);

        self::assertTrue($this->restriction->isMethodAllowed(7, 'checkmo'));
        self::assertFalse($this->restriction->isMethodAllowed(7, 'braintree'));
    }

    public function testACompanyThatRestrictsToNothingAllowsNothing(): void
    {
        $this->inCompany();
        $this->settingResolver->method('resolvePaymentMethods')->willReturn([]);

        self::assertFalse($this->restriction->isMethodAllowed(7, 'checkmo'));
    }

    public function testAnUnrestrictedCompanyAllowsEverything(): void
    {
        $this->inCompany();
        $this->settingResolver->method('resolvePaymentMethods')->willReturn(null);

        self::assertTrue($this->restriction->isMethodAllowed(7, 'anything'));
    }

    public function testACompanyThatIsNotActiveDoesNotRestrictItsPeople(): void
    {
        // Their company has no say until an administrator approves or unblocks it; they pay as an
        // ordinary customer rather than being locked out of checkout entirely.
        $this->inCompany(CompanyInterface::STATUS_BLOCKED);
        $this->settingResolver->expects(self::never())->method('resolvePaymentMethods');

        self::assertNull($this->restriction->getAllowedMethods(7));
    }

    public function testAMissingCompanyDoesNotRestrict(): void
    {
        $this->membership->method('getCompanyId')->willReturn(10);
        $this->companyRepository->method('getById')->willThrowException(new NoSuchEntityException());

        self::assertNull($this->restriction->getAllowedMethods(7));
    }

    public function testTheModuleBeingOffRestrictsNobody(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(false);
        $restriction = new PaymentRestriction(
            $this->membership,
            $this->companyRepository,
            $this->companyResource(),
            $this->settingResolver,
            $config
        );
        $this->membership->expects(self::never())->method('getCompanyId');

        self::assertNull($restriction->getAllowedMethods(7));
    }

    public function testTheAnswerIsCachedPerCustomer(): void
    {
        $this->inCompany();
        $this->settingResolver->expects(self::once())->method('resolvePaymentMethods')->willReturn(['checkmo']);

        $this->restriction->getAllowedMethods(7);
        $this->restriction->getAllowedMethods(7);
    }
}
