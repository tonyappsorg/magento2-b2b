<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RegisterHyvaTailwindSources implements ObserverInterface
{
    public function __construct(private readonly ComponentRegistrarInterface $componentRegistrar)
    {
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');
        if (!$config) {
            return;
        }

        $extensions = (array) $config->getData('extensions');
        $paths = [];
        foreach ($this->componentRegistrar->getPaths(ComponentRegistrar::MODULE) as $moduleName => $modulePath) {
            if (
                !str_starts_with($moduleName, 'Tonyapps_')
                || !str_starts_with($modulePath, BP . DIRECTORY_SEPARATOR)
                || !is_file($modulePath . '/view/frontend/tailwind/module.css')
            ) {
                continue;
            }

            $paths[] = ['src' => substr($modulePath, strlen(BP) + 1)];
        }

        $bySource = [];
        foreach (array_merge($extensions, $paths) as $extension) {
            if (isset($extension['src'])) {
                $bySource[$extension['src']] = $extension;
            }
        }

        $config->setData('extensions', array_values($bySource));
    }
}
