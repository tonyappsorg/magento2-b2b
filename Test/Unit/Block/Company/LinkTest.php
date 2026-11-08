<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Block\Company;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Block\Company\Delimiter;
use Tonyapps\B2b\Block\Company\Link;
use Tonyapps\B2b\Model\CompanyContext;

class LinkTest extends TestCase
{
    public function testSectionLinkIsHiddenWithoutItsPermission(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('isEnabled')->willReturn(true);
        $context->method('getCompany')->willReturn($this->createMock(CompanyInterface::class));
        $context->expects(self::once())->method('isAllowed')->with('users_view')->willReturn(false);

        self::assertSame('', $this->render($context, ['permission' => 'users_view']));
    }

    public function testLinkIsHiddenOutsideACompany(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('isEnabled')->willReturn(true);
        $context->method('getCompany')->willReturn(null);
        $context->expects(self::never())->method('isAllowed');

        self::assertSame('', $this->render($context, ['permission' => 'users_view']));
    }

    public function testDelimiterIsHiddenOutsideACompany(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('isEnabled')->willReturn(true);
        $context->method('getCompany')->willReturn(null);

        self::assertSame('', $this->render($context, [], Delimiter::class));
    }

    private function render(CompanyContext $context, array $data, string $class = Link::class): string
    {
        $link = (new ObjectManager($this))->getObject($class, ['companyContext' => $context, 'data' => $data]);
        $method = new \ReflectionMethod($link, '_toHtml');

        return $method->invoke($link);
    }
}
