<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Plugin\Customer;

use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Plugin\Customer\AddCompanyToForm;

class AddCompanyToFormTest extends TestCase
{
    public function testAddsCompanyIdsForReturnedCustomerRows(): void
    {
        $membership = $this->createMock(MembershipManagementInterface::class);
        $membership->expects(self::once())->method('getCompanyIds')->with([12, 14])->willReturn([12 => 5]);
        $plugin = new AddCompanyToForm($membership);

        $result = $plugin->afterGetData($this->createMock(DataProviderWithDefaultAddresses::class), [
            12 => ['email' => 'one@example.test'],
            14 => ['email' => 'two@example.test'],
        ]);

        self::assertSame(5, $result[12][AddCompanyToForm::DATA_SCOPE]['company_id']);
        self::assertSame('', $result[14][AddCompanyToForm::DATA_SCOPE]['company_id']);
        self::assertSame('one@example.test', $result[12]['email']);
    }

    public function testEmptyProviderResultDoesNotQueryMembership(): void
    {
        $membership = $this->createMock(MembershipManagementInterface::class);
        $membership->expects(self::never())->method('getCompanyIds');

        self::assertSame([], (new AddCompanyToForm($membership))->afterGetData(
            $this->createMock(DataProviderWithDefaultAddresses::class),
            []
        ));
    }
}
