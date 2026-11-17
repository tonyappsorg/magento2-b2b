<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Observer\RegisterHyvaTailwindSources;

class RegisterHyvaTailwindSourcesTest extends TestCase
{
    public function testAddsInstalledTonyappsTailwindSourcesAndKeepsExistingSources(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPaths')->with(ComponentRegistrar::MODULE)->willReturn([
            'Tonyapps_B2b' => BP . '/app/code/Tonyapps/B2b',
            'Tonyapps_B2bOrderApproval' => BP . '/app/code/Tonyapps/B2bOrderApproval',
            'Other_Module' => BP,
        ]);
        $config = new DataObject(['extensions' => [
            ['src' => 'vendor/hyva-themes/magento2-theme-module/src'],
        ]]);

        (new RegisterHyvaTailwindSources($registrar))->execute(new Observer(['config' => $config]));

        self::assertSame([
            ['src' => 'vendor/hyva-themes/magento2-theme-module/src'],
            ['src' => 'app/code/Tonyapps/B2b'],
            ['src' => 'app/code/Tonyapps/B2bOrderApproval'],
        ], array_values($config->getData('extensions')));
    }

    public function testDoesNotDuplicateAnExistingSource(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPaths')->with(ComponentRegistrar::MODULE)->willReturn([
            'Tonyapps_B2b' => BP . '/app/code/Tonyapps/B2b',
        ]);
        $config = new DataObject(['extensions' => [
            ['src' => 'app/code/Tonyapps/B2b'],
        ]]);

        (new RegisterHyvaTailwindSources($registrar))->execute(new Observer(['config' => $config]));

        self::assertSame([['src' => 'app/code/Tonyapps/B2b']], array_values($config->getData('extensions')));
    }
}
