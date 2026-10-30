<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Controller\Company;

use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\Company\Index;
use Tonyapps\B2b\Model\CompanyContext;

class IndexTest extends TestCase
{
    public function testAMemberOfACompanyAwaitingApprovalSeesTheDashboard(): void
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getStatus')->willReturn(CompanyInterface::STATUS_PENDING);
        $context = $this->createMock(CompanyContext::class);
        $context->method('isEnabled')->willReturn(true);
        $context->method('getCustomerId')->willReturn(7);
        $context->method('getCompany')->willReturn($company);
        // A pending company grants no permission at all; the dashboard must not ask for one.
        $context->method('isAllowed')->willReturn(false);
        $page = $this->createMock(Page::class);
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_PAGE)->willReturn($page);

        $result = (new Index(
            $this->createMock(RequestInterface::class),
            $resultFactory,
            $context,
            $this->createMock(Session::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(UrlInterface::class)
        ))->execute();

        self::assertSame($page, $result);
    }
}
