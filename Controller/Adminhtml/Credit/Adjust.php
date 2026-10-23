<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Credit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Adds or removes store credit by hand.
 */
class Adjust extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    public function __construct(
        Context $context,
        private readonly StoreCredit $storeCredit,
        private readonly AuthSession $authSession
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
     * Accepts both "1234.56" and the "1 234,56" a European admin is likely to type.
     */
    private function parseAmount(string $raw): ?float
    {
        $normalised = str_replace([' ', ','], ['', '.'], trim($raw));

        return is_numeric($normalised) ? abs((float)$normalised) : null;
    }
}
