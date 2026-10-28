<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Credit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Adds or removes store credit by hand.
 */
class Adjust extends Action implements HttpPostActionInterface
{
    // Moving money gets its own permission: managing companies doesn't imply it.
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::credit';

    public function __construct(
        Context $context,
        private readonly StoreCredit $storeCredit,
        private readonly AuthSession $authSession,
        private readonly ResolverInterface $localeResolver
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $companyId = (int)$this->getRequest()->getParam('company_id');

        if (!$companyId) {
            $this->messageManager->addErrorMessage(__('Pick a company to manage credit for.'));

            return $resultRedirect->setPath('tonyapps_b2b/company/index');
        }

        $amount = $this->parseAmount((string)$this->getRequest()->getParam('amount', ''));
        $direction = $this->getRequest()->getParam('direction') === 'subtract' ? -1 : 1;

        try {
            if ($amount === null) {
                throw new LocalizedException(__('Enter an amount as a number.'));
            }

            $balance = $this->storeCredit->adjust(
                $companyId,
                $direction * $amount,
                trim((string)$this->getRequest()->getParam('comment', '')),
                (int)$this->authSession->getUser()?->getId() ?: null
            );

            $this->messageManager->addSuccessMessage(
                __('Store credit updated. The balance is now %1.', number_format($balance, 2))
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('Could not update the store credit.'));
        }

        return $resultRedirect->setPath('tonyapps_b2b/credit/index', ['company_id' => $companyId]);
    }

    /**
     * Read the amount the way the admin's own locale writes numbers: "1,234.50" in English, "1.234,50" in
     * German. Plain "1234.5" works everywhere. The whole input has to be a number, so "12abc" is refused
     * rather than read as 12.
     */
    private function parseAmount(string $raw): ?float
    {
        $raw = trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', $raw));
        if ($raw === '') {
            return null;
        }
        foreach ([$this->localeResolver->getLocale(), 'en_US'] as $locale) {
            $position = 0;
            $value = (new \NumberFormatter($locale, \NumberFormatter::DECIMAL))
                ->parse($raw, \NumberFormatter::TYPE_DOUBLE, $position);
            if ($value !== false && $position === strlen($raw)) {
                return abs($value);
            }
        }

        return null;
    }
}
