<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Customer\Block\Account\SortLink;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\CompanyContext;

/**
 * "My Company" link in the customer account navigation.
 *
 * Extends the customer module's SortLink rather than the plain Current link: the account
 * navigation sorts only children that implement SortLinkInterface, and puts everything else after
 * them regardless of the sortOrder argument.
 *
 * Hidden for customers who are not in a company, so the account menu of a normal shopper is
 * unchanged by installing this module.
 */
class Link extends SortLink
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly CompanyContext $companyContext,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->companyContext->isEnabled() || $this->companyContext->getCompany() === null) {
            return '';
        }

        return parent::_toHtml();
    }
}
