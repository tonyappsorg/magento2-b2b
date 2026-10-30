<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\CustomerNameGenerationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;

/**
 * Transactional emails for the company approval workflow.
 *
 * Failures are logged, never thrown: a mail server being down must not roll back an approval the
 * administrator has already made, nor lose a registration the customer has already submitted.
 */
class CompanyEmail
{
    /**
     * Recipient of the "new company" notification: the store's General Contact, as configured
     * under Stores > Configuration > General > Store Email Addresses.
     */
    private const NOTIFICATION_IDENTITY = 'general';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly StateInterface $inlineTranslation,
        private readonly Config $config,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerNameGenerationInterface $customerNameGeneration,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Tell the store's General Contact that a company is waiting for approval.
     */
    public function sendNewCompanyNotification(CompanyInterface $company): void
    {
        $store = $this->getStore();
        $admin = $this->getAdminName($company);

        $this->send(
            $this->config->getNewCompanyTemplate((int)$store->getId()),
            (string)$store->getConfig('trans_email/ident_' . self::NOTIFICATION_IDENTITY . '/email'),
            (string)$store->getConfig('trans_email/ident_' . self::NOTIFICATION_IDENTITY . '/name'),
            $store,
            [
                'company_name' => (string)$company->getName(),
                'company_email' => (string)$company->getData(CompanyInterface::EMAIL),
                'admin_name' => $admin['name'],
                'admin_email' => $admin['email'],
                'admin_url' => $this->urlBuilder->getUrl(
                    'tonyapps_b2b/company/edit',
                    ['entity_id' => $company->getId(), '_nosid' => true]
                ),
            ]
        );
    }

    public function sendApproved(CompanyInterface $company): void
    {
        $this->sendDecision($company, true);
    }

    public function sendRejected(CompanyInterface $company): void
    {
        $this->sendDecision($company, false);
    }

    private function sendDecision(CompanyInterface $company, bool $approved): void
    {
        $store = $this->getStore();
        $admin = $this->getAdminName($company);
        if ($admin['email'] === '') {
            return;
        }

        $templateVars = [
            'company_name' => (string)$company->getName(),
            'admin_name' => $admin['name'],
        ];
        if ($approved) {
            $templateVars['company_url'] = $store->getUrl('b2b/company/index');
        }

        $this->send(
            $approved
                ? $this->config->getApprovedTemplate((int)$store->getId())
                : $this->config->getRejectedTemplate((int)$store->getId()),
            $admin['email'],
            $admin['name'],
            $store,
            $templateVars
        );
    }

    /**
     * @param array<string, string> $templateVars
     */
    private function send(
        string $template,
        string $toEmail,
        string $toName,
        Store $store,
        array $templateVars
    ): void {
        if ($template === '' || $toEmail === '') {
            return;
        }

        try {
            $this->inlineTranslation->suspend();
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope(self::NOTIFICATION_IDENTITY, $store->getId())
                ->addTo($toEmail, $toName)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf('Tonyapps_B2b could not send "%s" to %s: %s', $template, $toEmail, $exception->getMessage())
            );
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Name and email of the company's administrator, empty when it has none yet.
     *
     * @return array{name: string, email: string}
     */
    private function getAdminName(CompanyInterface $company): array
    {
        $customerId = $company->getAdminCustomerId();
        if ($customerId === null) {
            return ['name' => '', 'email' => ''];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException) {
            return ['name' => '', 'email' => ''];
        }

        return [
            'name' => $this->customerNameGeneration->getCustomerName($customer),
            'email' => (string)$customer->getEmail(),
        ];
    }

    /**
     * Store whose templates and email identities to use.
     *
     * Approvals happen in the admin area, where the current store is the admin store (0) and has
     * no usable frontend config, so fall back to the default store view. Companies are global in
     * this module — if they ever become per-website, this is the place that has to change.
     */
    private function getStore(): Store
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        if ((int)$store->getId() === Store::DEFAULT_STORE_ID) {
            /** @var Store $store */
            $store = $this->storeManager->getDefaultStoreView() ?? $store;
        }

        return $store;
    }
}
