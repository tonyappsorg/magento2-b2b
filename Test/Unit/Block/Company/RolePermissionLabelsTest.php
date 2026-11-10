<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Block\Company;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Block\Company\Roles;
use Tonyapps\B2b\Model\Permissions;

class RolePermissionLabelsTest extends TestCase
{
    public function testTheRoleListNamesTheRolesPermissions(): void
    {
        $permissions = $this->createMock(Permissions::class);
        $permissions->method('getLabels')->willReturn([
            'orders_view_company' => 'See orders placed by everyone in the company',
            'credit_view' => 'See the store credit balance and history',
            'users_manage' => 'Add and remove company users',
        ]);
        $role = $this->createMock(RoleInterface::class);
        $role->method('hasPermission')->willReturnCallback(
            static fn(string $code): bool => in_array($code, ['orders_view_company', 'credit_view'], true)
        );
        $block = (new ObjectManager($this))->getObject(Roles::class, ['permissions' => $permissions]);

        self::assertSame(
            'See orders placed by everyone in the company, See the store credit balance and history',
            $block->getPermissionLabels($role)
        );
    }

    public function testARoleWithoutPermissionsSaysSo(): void
    {
        $permissions = $this->createMock(Permissions::class);
        $permissions->method('getLabels')->willReturn(['credit_view' => 'See the store credit balance and history']);
        $role = $this->createMock(RoleInterface::class);
        $role->method('hasPermission')->willReturn(false);
        $block = (new ObjectManager($this))->getObject(Roles::class, ['permissions' => $permissions]);

        self::assertSame('Nothing beyond seeing the company', $block->getPermissionLabels($role));
    }
}
