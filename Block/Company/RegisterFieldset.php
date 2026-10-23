<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\Config;

/**
 * The optional company section on the Create an Account form.
 */
class RegisterFieldset extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        return $this->config->isOfferedDuringCustomerRegistration() ? parent::_toHtml() : '';
    }
}
