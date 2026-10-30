<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Observer\SaveCustomerCompany;

class SaveCustomerCompanyTest extends TestCase
{
    private function save(bool $allowed, MembershipManagementInterface $membership): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getPostValue')->willReturn(['company_id' => '4']);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with('Tonyapps_B2b::companies')->willReturn($allowed);

        (new SaveCustomerCompany($membership, $authorization))->execute(
            new Observer(['event' => new Event(['request' => $request, 'customer' => $customer])])
        );
    }

    public function testAnAdminWithoutTheCompaniesResourceCannotAssignACompany(): void
    {
        $membership = $this->createMock(MembershipManagementInterface::class);
        $membership->expects(self::never())->method('assign');

        $this->save(false, $membership);
    }

    public function testAnAdminWithTheCompaniesResourceAssignsTheCompany(): void
    {
        $membership = $this->createMock(MembershipManagementInterface::class);
        $membership->expects(self::once())->method('assign')->with(7, 4);

        $this->save(true, $membership);
    }
}
