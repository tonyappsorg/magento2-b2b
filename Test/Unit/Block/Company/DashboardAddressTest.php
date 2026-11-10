<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Block\Company;

use Magento\Directory\Model\Country;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Block\Company\Dashboard;
use Tonyapps\B2b\Model\Company;
use Tonyapps\B2b\Model\CompanyContext;

class DashboardAddressTest extends TestCase
{
    public function testTheLegalAddressNamesTheCountry(): void
    {
        $company = (new ObjectManager($this))->getObject(Company::class);
        $company->setData([
            CompanyInterface::STREET => '1 Test Street',
            CompanyInterface::CITY => 'Austin',
            CompanyInterface::POSTCODE => '73301',
            CompanyInterface::COUNTRY_ID => 'US',
        ]);
        $context = $this->createMock(CompanyContext::class);
        $context->method('getCompany')->willReturn($company);
        $country = $this->createMock(Country::class);
        $country->method('loadByCode')->with('US')->willReturnSelf();
        $country->method('getName')->willReturn('United States');
        $countryFactory = $this->createMock(CountryFactory::class);
        $countryFactory->method('create')->willReturn($country);

        $block = (new ObjectManager($this))->getObject(Dashboard::class, [
            'companyContext' => $context,
            'countryFactory' => $countryFactory,
        ]);

        self::assertSame(['1 Test Street', 'Austin, 73301', 'United States'], $block->getAddressLines());
    }
}
