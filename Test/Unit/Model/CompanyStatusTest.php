<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyEmail;
use Tonyapps\B2b\Model\CompanyStatus;
use Tonyapps\B2b\Model\CustomerGroupSync;

class CompanyStatusTest extends TestCase
{
    public function testTheGroupBeforeTheSaveIsHandedToTheSync(): void
    {
        $sync = $this->createMock(CustomerGroupSync::class);
        $sync->expects(self::once())->method('syncCompanyTree')->with(4, 5);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(4);

        (new CompanyStatus(
            $this->createMock(CompanyRepositoryInterface::class),
            $sync,
            $this->createMock(CompanyEmail::class)
        ))->applySideEffects($company, CompanyInterface::STATUS_ACTIVE, CompanyInterface::STATUS_ACTIVE, 5);
    }
}
