<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Behaviour that lives in XML: what the payment Adapter reads, what the role grid keeps on reload,
 * and which storefront pages must never reach the full page cache.
 */
class ConfigurationTest extends TestCase
{
    private const MODULE_DIR = __DIR__ . '/../..';

    public function testTheStoreCreditMethodCanBeOfferedAtCheckoutAndInTheAdmin(): void
    {
        // Adapter::canUseCheckout()/canUseInternal() read these; missing means the method never shows.
        $method = simplexml_load_file(self::MODULE_DIR . '/etc/config.xml')->default->payment->tonyapps_b2b_credit;

        self::assertSame('1', (string)$method->can_use_checkout);
        self::assertSame('1', (string)$method->can_use_internal);
    }

    public function testTheRoleGridKeepsItsCompanyScopeOnReload(): void
    {
        $xml = simplexml_load_file(self::MODULE_DIR . '/view/adminhtml/ui_component/tonyapps_b2b_role_listing.xml');
        $params = $xml->xpath('//dataSource/settings/filterUrlParams/param[@name="company_id"]');

        self::assertCount(1, $params);
        self::assertSame('*', (string)$params[0]);
    }

    public function testEveryCompanyPageIsKeptOutOfTheFullPageCache(): void
    {
        $layouts = glob(self::MODULE_DIR . '/view/frontend/layout/b2b_*.xml');
        self::assertNotEmpty($layouts);

        foreach ($layouts as $layout) {
            $blocks = simplexml_load_file($layout)->xpath('//referenceContainer[@name="content"]/block');
            self::assertNotEmpty($blocks, basename($layout));
            self::assertSame('false', (string)$blocks[0]['cacheable'], basename($layout));
        }
    }
}
