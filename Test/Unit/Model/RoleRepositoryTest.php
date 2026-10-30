<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;
use Tonyapps\B2b\Model\ResourceModel\Role\CollectionFactory;
use Tonyapps\B2b\Model\Role;
use Tonyapps\B2b\Model\RoleFactory;
use Tonyapps\B2b\Model\RoleRepository;
use Tonyapps\B2b\Model\SettingResolver;

class RoleRepositoryTest extends TestCase
{
    private RoleResource&MockObject $resource;
    private RoleRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(RoleResource::class);

        $this->repository = new RoleRepository(
            $this->resource,
            $this->createMock(RoleFactory::class),
            $this->createMock(CollectionFactory::class),
            new Permissions(),
            $this->createMock(CompanyResource::class),
            $this->createMock(SettingResolver::class)
        );
    }

    private function role(string $name = 'Buyer', int $companyId = 10, int $roleId = 3): Role&MockObject
    {
        $role = $this->createMock(Role::class);
        $role->method('getRoleName')->willReturn($name);
        $role->method('getCompanyId')->willReturn($companyId);
        $role->method('getId')->willReturn($roleId);
        $role->method('getPermissions')->willReturn([]);

        return $role;
    }

    public function testANamelessRoleIsRefused(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($this->role('   '));
    }

    public function testARoleWithoutACompanyIsRefused(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($this->role('Buyer', 0));
    }

    public function testPermissionsAreNormalisedOnTheWayIn(): void
    {
        $role = $this->createMock(Role::class);
        $role->method('getRoleName')->willReturn('Buyer');
        $role->method('getCompanyId')->willReturn(10);
        $role->method('getId')->willReturn(3);
        $role->method('getPermissions')->willReturn([Permissions::USERS_MANAGE, 'nonsense']);

        // Whatever the caller sent, what is stored is the cleaned list.
        $role->expects(self::once())->method('setPermissions')
            ->with([Permissions::USERS_VIEW, Permissions::USERS_MANAGE]);

        $this->repository->save($role);
    }

    /**
     * Regression: the "one default per company" rule used to live in the resource model's
     * _afterSave, which AbstractDb::save() never reaches when the model reports no data changes.
     * Re-marking an already-flagged object as default then left the company with no default at
     * all. The flag is now forced on every save.
     */
    public function testTheDefaultFlagIsForcedEvenWhenTheModelLooksUnchanged(): void
    {
        $role = $this->role();
        $role->method('isDefault')->willReturn(true);
        $this->resource->method('save')->willReturnSelf();

        $this->resource->expects(self::once())->method('syncDefaultFlag')->with(10, 3, true);

        $this->repository->save($role);
    }

    public function testUnmarkingTheDefaultIsAlsoForced(): void
    {
        $role = $this->role();
        $role->method('isDefault')->willReturn(false);
        $this->resource->method('save')->willReturnSelf();

        $this->resource->expects(self::once())->method('syncDefaultFlag')->with(10, 3, false);

        $this->repository->save($role);
    }

    public function testARoleStillHeldByMembersCannotBeDeleted(): void
    {
        $role = $this->role();
        $this->resource->method('countMembers')->with(3)->willReturn(2);
        $this->resource->expects(self::never())->method('delete');

        $this->expectException(CouldNotDeleteException::class);
        $this->repository->delete($role);
    }

    public function testAnUnusedRoleIsDeleted(): void
    {
        $role = $this->role();
        $this->resource->method('countMembers')->willReturn(0);
        $this->resource->expects(self::once())->method('delete')->with($role);

        self::assertTrue($this->repository->delete($role));
    }
}
