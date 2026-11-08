<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\View;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\Dom\UrnResolver;
use PHPUnit\Framework\TestCase;

class LayoutSchemaTest extends TestCase
{
    private const SCHEMAS = [
        'page' => 'urn:magento:framework:View/Layout/etc/page_configuration.xsd',
        'layout' => 'urn:magento:framework:View/Layout/etc/layout_generic.xsd',
    ];

    public function testLayoutsMatchSchema(): void
    {
        $resolver = new UrnResolver();
        $files = glob(__DIR__ . '/../../../view/*/layout/*.xml');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $dom = new \DOMDocument();
            $dom->load($file);
            $schema = $resolver->getRealPath(self::SCHEMAS[$dom->documentElement->nodeName]);
            $this->assertSame([], Dom::validateDomDocument($dom, $schema), basename($file));
        }
    }
}
