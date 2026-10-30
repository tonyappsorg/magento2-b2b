<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Customer\Api\CustomerNameGenerationInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Company;
use Tonyapps\B2b\Model\CompanyEmail;
use Tonyapps\B2b\Model\Config;

class CompanyEmailTest extends TestCase
{
    public function testTheNewCompanyEmailLinksToTheAdminCompanyPage(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getConfig')->willReturn('owner@example.com');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $config = $this->createMock(Config::class);
        $config->method('getNewCompanyTemplate')->willReturn('tonyapps_b2b_email_new_company_template');

        $backendUrl = $this->createMock(BackendUrlInterface::class);
        $backendUrl->expects(self::once())->method('getUrl')
            ->with('tonyapps_b2b/company/edit', ['entity_id' => 4, '_nosid' => true, '_nosecret' => true])
            ->willReturn('https://shop.test/admin/tonyapps_b2b/company/edit/entity_id/4/');

        $vars = null;
        $transportBuilder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setFromByScope', 'addTo'] as $method) {
            $transportBuilder->method($method)->willReturnSelf();
        }
        $transportBuilder->method('setTemplateVars')->willReturnCallback(
            function (array $templateVars) use (&$vars, $transportBuilder): TransportBuilder {
                $vars = $templateVars;

                return $transportBuilder;
            }
        );
        $transportBuilder->method('getTransport')->willReturn($this->createMock(TransportInterface::class));

        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn(4);
        $company->method('getName')->willReturn('Acme');
        $company->method('getAdminCustomerId')->willReturn(null);

        (new CompanyEmail(
            $transportBuilder,
            $storeManager,
            $this->createMock(StateInterface::class),
            $config,
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(CustomerNameGenerationInterface::class),
            $backendUrl,
            $this->createMock(LoggerInterface::class)
        ))->sendNewCompanyNotification($company);

        self::assertSame('https://shop.test/admin/tonyapps_b2b/company/edit/entity_id/4/', $vars['admin_url']);
    }
}
