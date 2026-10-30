<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\Company;

class CompanyTest extends TestCase
{
    public function testApplyCustomerDataTrimsValuesAndIgnoresProtectedFields(): void
    {
        $company = (new ObjectManager($this))->getObject(Company::class);

        $company->applyCustomerData([
            CompanyInterface::NAME => '  Acme  ',
            CompanyInterface::EMAIL => '  contact@example.com ',
            CompanyInterface::STREET => [' 1 Main St ', '', ' Suite 2 '],
            CompanyInterface::STATUS => CompanyInterface::STATUS_ACTIVE,
            CompanyInterface::PARENT_ID => 45,
        ]);

        self::assertSame('Acme', $company->getName());
        self::assertSame('contact@example.com', $company->getData(CompanyInterface::EMAIL));
        self::assertSame("1 Main St\nSuite 2", $company->getData(CompanyInterface::STREET));
        self::assertNull($company->getData(CompanyInterface::STATUS));
        self::assertNull($company->getData(CompanyInterface::PARENT_ID));
    }
}
