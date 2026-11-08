<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Customer\Block\Account\Delimiter as CoreDelimiter;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\CompanyContext;

/**
 * Separator around the company section of the account navigation.
 *
 * Hidden for customers who are not in a company, like the company links, so a normal shopper's
 * menu does not get an empty section.
 */
class Delimiter extends CoreDelimiter
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->companyContext->isEnabled() || $this->companyContext->getCompany() === null) {
            return '';
        }

        return parent::_toHtml();
    }
}
