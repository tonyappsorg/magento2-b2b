<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Controller\Role;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Controller\Role\Delete;
use Tonyapps\B2b\Controller\Role\Save;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\PermissionChecker;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\RoleFactory;

/**
 * A member who manages roles must not use that to raise their own rights.
 */
class SaveTest extends TestCase
{
    private Http&MockObject $request;
    private ResultFactory&MockObject $resultFactory;
    private CompanyContext&MockObject $companyContext;
    private ManagerInterface&MockObject $messageManager;
    private FormKeyValidator&MockObject $formKeyValidator;
    private RoleRepositoryInterface&MockObject $roleRepository;
    private PermissionChecker&MockObject $permissionChecker;
    private RoleInterface&MockObject $role;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->resultFactory->method('create')->willReturn($redirect);

        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(10);
        $this->companyContext = $this->createMock(CompanyContext::class);
        $this->companyContext->method('isEnabled')->willReturn(true);
        $this->companyContext->method('getCustomerId')->willReturn(7);
        $this->companyContext->method('getCompany')->willReturn($company);
        $this->companyContext->method('isAllowed')->willReturn(true);

        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(true);

        $this->role = $this->createMock(RoleInterface::class);
        $this->role->method('getCompanyId')->willReturn(10);
        $this->role->method('getId')->willReturn(3);
        $this->roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $this->roleRepository->method('getById')->with(3)->willReturn($this->role);
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
    }

    private function save(array $post): void
    {
        $this->request->method('getPostValue')->with('role')->willReturn($post);

        (new Save(
            $this->request,
            $this->resultFactory,
            $this->companyContext,
            $this->createMock(Session::class),
            $this->messageManager,
            $this->createMock(UrlInterface::class),
            $this->formKeyValidator,
            $this->roleRepository,
            $this->createMock(RoleFactory::class),
            $this->createMock(LoggerInterface::class),
            $this->permissionChecker
        ))->execute();
    }

    public function testEditingTheRoleYouHoldIsRefused(): void
    {
        $this->permissionChecker->method('holdsRole')->with(7, self::anything(), 3)->willReturn(true);
        $this->permissionChecker->method('canGrant')->willReturn(true);
        $this->roleRepository->expects(self::never())->method('save');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $this->save(['role_id' => 3, 'role_name' => 'Buyer', 'permissions' => [Permissions::USERS_VIEW]]);
    }

    public function testGrantingAPermissionYouDoNotHoldIsRefused(): void
    {
        $this->permissionChecker->method('holdsRole')->willReturn(false);
        $this->permissionChecker->method('canGrant')
            ->with(7, self::anything(), [Permissions::CREDIT_USE])->willReturn(false);
        $this->roleRepository->expects(self::never())->method('save');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $this->save(['role_id' => 3, 'role_name' => 'Buyer', 'permissions' => [Permissions::CREDIT_USE]]);
    }

    public function testARoleWithinYourOwnRightsIsSaved(): void
    {
        $this->permissionChecker->method('holdsRole')->willReturn(false);
        $this->permissionChecker->method('canGrant')->willReturn(true);
        $this->role->expects(self::once())->method('setPermissions')->with([Permissions::USERS_VIEW]);
        $this->roleRepository->expects(self::once())->method('save')->with($this->role);

        $this->save(['role_id' => 3, 'role_name' => 'Buyer', 'permissions' => [Permissions::USERS_VIEW]]);
    }

    public function testDeletingTheRoleYouHoldIsRefused(): void
    {
        $this->request->method('getParam')->with('role_id')->willReturn('3');
        $this->permissionChecker->method('holdsRole')->with(7, self::anything(), 3)->willReturn(true);
        $this->roleRepository->expects(self::never())->method('delete');

        (new Delete(
            $this->request,
            $this->resultFactory,
            $this->companyContext,
            $this->createMock(Session::class),
            $this->messageManager,
            $this->createMock(UrlInterface::class),
            $this->formKeyValidator,
            $this->roleRepository,
            $this->permissionChecker
        ))->execute();
    }
}
