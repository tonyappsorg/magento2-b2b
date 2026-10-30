<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Model\Permissions;

class PermissionsTest extends TestCase
{
    private Permissions $permissions;

    protected function setUp(): void
    {
        $this->permissions = new Permissions();
    }

    public function testUnknownPermissionsAreDropped(): void
    {
        self::assertSame(
            [Permissions::PROFILE_VIEW],
            $this->permissions->normalise([Permissions::PROFILE_VIEW, 'delete_everything', ''])
        );
    }

    public function testADependentPermissionPullsInTheOneItNeeds(): void
    {
        // "edit the profile" without "view the profile" would be a role that cannot reach the page
        // it is allowed to change.
        self::assertSame(
            [Permissions::PROFILE_VIEW, Permissions::PROFILE_EDIT],
            $this->permissions->normalise([Permissions::PROFILE_EDIT])
        );
    }

    public function testEveryManagePermissionImpliesItsViewPermission(): void
    {
        $granted = $this->permissions->normalise([Permissions::USERS_MANAGE, Permissions::ROLES_MANAGE]);

        self::assertContains(Permissions::USERS_VIEW, $granted);
        self::assertContains(Permissions::ROLES_VIEW, $granted);
    }

    public function testOrderIsTheCatalogueOrderWhateverTheInputOrder(): void
    {
        self::assertSame(
            $this->permissions->normalise([Permissions::PROFILE_VIEW, Permissions::ROLES_VIEW]),
            $this->permissions->normalise([Permissions::ROLES_VIEW, Permissions::PROFILE_VIEW])
        );
    }

    public function testNormalisingIsStable(): void
    {
        $once = $this->permissions->normalise([Permissions::USERS_MANAGE]);

        self::assertSame($once, $this->permissions->normalise($once));
    }

    public function testEveryGroupedPermissionIsInGetAll(): void
    {
        $grouped = [];
        foreach ($this->permissions->getGrouped() as $group) {
            $grouped = array_merge($grouped, array_keys($group));
        }

        self::assertSame($grouped, $this->permissions->getAll());
    }

    public function testLabelsFlattensGroupedPermissionLabels(): void
    {
        $grouped = [];
        foreach ($this->permissions->getGrouped() as $group) {
            foreach ($group as $permission => $label) {
                $grouped[$permission] = (string)$label;
            }
        }

        self::assertSame($grouped, $this->permissions->getLabels());
    }

    public function testRequisitionListManagePermissionImpliesView(): void
    {
        self::assertSame(
            [Permissions::REQUISITION_LISTS_VIEW, Permissions::REQUISITION_LISTS_MANAGE],
            $this->permissions->normalise([Permissions::REQUISITION_LISTS_MANAGE])
        );
    }
}
