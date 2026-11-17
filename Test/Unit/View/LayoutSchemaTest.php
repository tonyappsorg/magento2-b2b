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

    public function testCompanyPagesUseHyvaControlsAndRegisterTheirTailwindSources(): void
    {
        $root = dirname(__DIR__, 3);
        $templates = [
            'company/dashboard.phtml' => ['btn-primary', 'card', 'grid-cols-1', 'company-actions', 'gap-2'],
            'company/users.phtml' => ['form-input', 'form-select', 'btn-primary'],
            'company/roles.phtml' => ['form-input', 'form-checkbox', 'btn-primary', 'data-icon="pencil"', 'data-icon="trash"', 'aria-label'],
            'company/children.phtml' => ['form-input', 'form-select', 'btn-primary'],
            'company/orders.phtml' => ['w-full', 'table-wrapper'],
            'company/credit.phtml' => ['w-full', 'table-wrapper'],
            'company/form.phtml' => ['form-input', 'form-select', 'btn-primary'],
            'company/register_fieldset.phtml' => ['form-input', 'form-checkbox'],
        ];

        foreach ($templates as $template => $classes) {
            $contents = file_get_contents($root . '/view/frontend/templates/' . $template);
            foreach ($classes as $class) {
                self::assertStringContainsString($class, $contents, $template);
            }
        }

        self::assertStringContainsString(
            '@source "../templates/**/*.phtml"',
            file_get_contents($root . '/view/frontend/tailwind/module.css')
        );
        $styles = file_get_contents($root . '/view/frontend/tailwind/module.css');
        self::assertStringContainsString('px-2 py-2 text-left text-sm font-semibold', $styles);
        self::assertStringContainsString('tbody tr:nth-child(even)', $styles);
    }
}
