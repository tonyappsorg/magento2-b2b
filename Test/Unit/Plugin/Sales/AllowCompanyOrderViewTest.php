<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Plugin\Sales;

use Magento\Sales\Controller\AbstractController\OrderViewAuthorization;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Plugin\Sales\AllowCompanyOrderView;

class AllowCompanyOrderViewTest extends TestCase
{
    public function testCorePermissionIsPreservedWithoutCompanyChecks(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->expects(self::never())->method('isAllowed');
        $plugin = new AllowCompanyOrderView($context, $this->createMock(CompanyResource::class), $this->createMock(OrderConfig::class));

        self::assertTrue($plugin->afterCanView($this->createMock(OrderViewAuthorization::class), true, $this->createMock(Order::class)));
    }

    public function testCompanyPermissionOnlyAllowsVisibleOrdersInOwnSubtree(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('isAllowed')->with(Permissions::ORDERS_VIEW_COMPANY)->willReturn(true);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(5);
        $context->method('getCompany')->willReturn($company);
        $companyResource = $this->createMock(CompanyResource::class);
        $companyResource->method('getSubtreeIds')->with(5)->willReturn([5, 8]);
        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getVisibleOnFrontStatuses')->willReturn(['processing', 'complete']);
        $plugin = new AllowCompanyOrderView($context, $companyResource, $orderConfig);
        $visibleOrder = $this->createMock(Order::class);
        $visibleOrder->method('getData')->with('company_id')->willReturn(8);
        $visibleOrder->method('getStatus')->willReturn('processing');

        self::assertTrue($plugin->afterCanView($this->createMock(OrderViewAuthorization::class), false, $visibleOrder));
        $hiddenOrder = $this->createMock(Order::class);
        $hiddenOrder->method('getData')->with('company_id')->willReturn(8);
        $hiddenOrder->method('getStatus')->willReturn('pending');
        self::assertFalse($plugin->afterCanView($this->createMock(OrderViewAuthorization::class), false, $hiddenOrder));
    }

    public function testCompanyMemberCannotSeeForeignCompanyOrder(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('isAllowed')->willReturn(true);
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(5);
        $context->method('getCompany')->willReturn($company);
        $resource = $this->createMock(CompanyResource::class);
        $resource->expects(self::once())->method('getSubtreeIds')->with(5)->willReturn([5, 8]);
        $order = $this->createMock(Order::class);
        $order->method('getData')->with('company_id')->willReturn(99);

        $plugin = new AllowCompanyOrderView($context, $resource, $this->createMock(OrderConfig::class));
        self::assertFalse($plugin->afterCanView($this->createMock(OrderViewAuthorization::class), false, $order));
    }
}
