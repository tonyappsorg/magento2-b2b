<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Customer\NotificationStorage;
use Magento\Customer\Model\Indexer\Processor as CustomerIndexer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CustomerGroupSync;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;
use Tonyapps\B2b\Model\SettingResolver;

/**
 * Covers who gets moved between customer groups, which is the part that changes prices.
 */
class CustomerGroupSyncTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private CompanyResource&MockObject $companyResource;
    private CompanyCustomer&MockObject $companyCustomer;
    private SettingResolver&MockObject $settingResolver;
    private CustomerIndexer&MockObject $customerIndexer;
    private CustomerGroupSync $sync;
    private NotificationStorage&MockObject $notificationStorage;
    private int $currentGroupId = 0;

    /**
     * @var array<int, array{ids: int[], groupId: int}>
     */
    private array $updates = [];

    /**
     * Customer IDs the statement under test last filtered on.
     *
     * @var int[]
     */
    private array $selectedCustomerIds = [];

    /**
     * Customers a "group_id = ?" lookup finds still in the group it asks about.
     *
     * @var int[]
     */
    private array $inQueriedGroup = [];
    private bool $filtersOnGroup = false;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->companyResource = $this->createMock(CompanyResource::class);
        $this->companyCustomer = $this->createMock(CompanyCustomer::class);
        $this->settingResolver = $this->createMock(SettingResolver::class);
        $this->customerIndexer = $this->createMock(CustomerIndexer::class);
        $this->notificationStorage = $this->createMock(NotificationStorage::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use ($select): Select {
                if ($condition === 'entity_id IN (?)') {
                    $this->selectedCustomerIds = $value;
                    $this->filtersOnGroup = false;
                }
                if ($condition === 'group_id = ?') {
                    $this->filtersOnGroup = true;
                }

                return $select;
            }
        );
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturnCallback(fn(): int => $this->currentGroupId);
        // Every customer applyGroup looks at is treated as not being in the target group yet.
        $this->connection->method('fetchCol')->willReturnCallback(
            fn(): array => $this->filtersOnGroup
                ? array_values(array_intersect($this->selectedCustomerIds, $this->inQueriedGroup))
                : $this->selectedCustomerIds
        );
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $bind, array $where): int {
                $this->updates[] = [
                    'ids' => $where['entity_id IN (?)'],
                    'groupId' => (int)$bind['group_id'],
                ];

                return count($where['entity_id IN (?)']);
            }
        );

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturn('customer_entity');

        $defaultGroup = $this->createMock(GroupInterface::class);
        $defaultGroup->method('getId')->willReturn(1);
        $groupManagement = $this->createMock(GroupManagementInterface::class);
        $groupManagement->method('getDefaultGroup')->willReturn($defaultGroup);

        $this->sync = new CustomerGroupSync(
            $resourceConnection,
            $this->companyResource,
            $this->companyCustomer,
            $this->settingResolver,
            $groupManagement,
            $this->customerIndexer,
            $this->notificationStorage
        );
    }

    public function testSubtreeMembersGetTheGroupTheirOwnCompanyResolvesTo(): void
    {
        // Child 4 sets group 5 of its own; grandchild 9 inherits it; root 1 resolves to group 2.
        $this->companyResource->method('getSubtree')->with(1)->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
            4 => ['path' => '1/4', 'status' => CompanyInterface::STATUS_ACTIVE],
            9 => ['path' => '1/4/9', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => 2, 4 => 5, 9 => 5]);
        $this->companyCustomer->method('getMemberIds')->willReturnCallback(
            fn(array $companyIds): array => $companyIds === [1] ? [100] : [200, 300]
        );

        $this->sync->syncCompanyTree(1);

        self::assertSame(
            [
                ['ids' => [100], 'groupId' => 2],
                ['ids' => [200, 300], 'groupId' => 5],
            ],
            $this->updates
        );
    }

    public function testCompaniesThatResolveToNoGroupLeaveTheirMembersAlone(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => null]);
        $this->companyCustomer->expects(self::never())->method('getMemberIds');

        $this->sync->syncCompanyTree(1);

        self::assertSame([], $this->updates);
    }

    public function testAPendingCompanyPutsItsMembersInTheDefaultGroupInsteadOfItsOwn(): void
    {
        // The point of the rule: a self-registered company must not hand itself wholesale
        // pricing before an administrator has approved it.
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_PENDING],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => 5]);
        $this->companyCustomer->method('getMemberIds')->with([1])->willReturn([100]);

        $this->sync->syncCompanyTree(1);

        self::assertSame([['ids' => [100], 'groupId' => 1]], $this->updates);
    }

    public function testOnlyTheActiveCompaniesInASubtreeConferTheirGroup(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
            4 => ['path' => '1/4', 'status' => CompanyInterface::STATUS_BLOCKED],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => 5, 4 => 5]);
        $this->companyCustomer->method('getMemberIds')->willReturnCallback(
            fn(array $companyIds): array => $companyIds === [1] ? [100] : [200]
        );

        $this->sync->syncCompanyTree(1);

        self::assertSame(
            [
                ['ids' => [100], 'groupId' => 5],
                ['ids' => [200], 'groupId' => 1],
            ],
            $this->updates
        );
    }

    public function testReleasingATreeIgnoresStatusAndSendsEveryoneToTheDefaultGroup(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => 5]);
        $this->companyCustomer->method('getMemberIds')->with([1])->willReturn([100, 200]);

        $this->sync->releaseCompanyTree(1);

        self::assertSame([['ids' => [100, 200], 'groupId' => 1]], $this->updates);
    }

    public function testJoiningACompanyWithNoGroupDoesNotTouchTheCustomersGroup(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(null);

        $this->sync->syncCustomer(7, 4);

        self::assertSame([], $this->updates);
    }

    public function testJoiningAnActiveCompanyAppliesItsGroup(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->companyResource->method('isActivePath')->willReturn(true);
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(5);

        $this->sync->syncCustomer(7, 4);

        self::assertSame([['ids' => [7], 'groupId' => 5]], $this->updates);
    }

    public function testJoiningACompanyAwaitingApprovalDoesNotGrantItsGroup(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->companyResource->method('isActivePath')->willReturn(false);
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(5);

        $this->sync->syncCustomer(7, 4);

        self::assertSame([['ids' => [7], 'groupId' => 1]], $this->updates);
    }

    public function testLeavingACompanyTakesBackTheGroupItGave(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(5);
        $this->currentGroupId = 5;

        $this->sync->syncCustomer(7, null, 4);

        self::assertSame([['ids' => [7], 'groupId' => 1]], $this->updates);
    }

    public function testLeavingACompanyKeepsAGroupSetByHand(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(5);
        $this->currentGroupId = 3; // an administrator moved them to "Retailer" by hand

        $this->sync->syncCustomer(7, null, 4);

        self::assertSame([], $this->updates);
    }

    public function testLeavingACompanyThatGaveNoGroupLeavesTheCustomerAlone(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(null);
        $this->currentGroupId = 3;

        $this->sync->syncCustomer(7, null, 4);

        self::assertSame([], $this->updates);
    }

    public function testMovingToACompanyWithNoGroupDropsTheOldCompanysPricing(): void
    {
        $this->companyResource->method('getPathById')->willReturnMap([[4, '1/4'], [8, '8']]);
        $this->settingResolver->method('resolveCustomerGroupId')->willReturnMap([['1/4', 5], ['8', null]]);
        $this->currentGroupId = 5;

        $this->sync->syncCustomer(7, 8, 4);

        self::assertSame([['ids' => [7], 'groupId' => 1]], $this->updates);
    }

    public function testChangedCustomersGetTheirSessionRefreshed(): void
    {
        $this->companyResource->method('getPathById')->willReturn('1/4');
        $this->companyResource->method('isActivePath')->willReturn(true);
        $this->settingResolver->method('resolveCustomerGroupId')->willReturn(5);
        $this->notificationStorage->expects(self::once())->method('add')
            ->with(NotificationStorage::UPDATE_CUSTOMER_SESSION, 7);

        $this->sync->syncCustomer(7, 4);
    }

    public function testDeletingACompanyThatManagedNoGroupLeavesItsMembersAlone(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => null]);
        $this->companyCustomer->expects(self::never())->method('getMemberIds');

        $this->sync->releaseCompanyTree(1);

        self::assertSame([], $this->updates);
    }

    public function testAnActiveCompanyUnderABlockedParentConfersNoGroup(): void
    {
        // Re-syncing child 4 alone: its own row is active, but parent 1 above the subtree is blocked.
        $this->companyResource->method('getSubtree')->with(4)->willReturn([
            4 => ['path' => '1/4', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->companyResource->expects(self::once())->method('getStatusesByIds')->with([1])
            ->willReturn([1 => CompanyInterface::STATUS_BLOCKED]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([4 => 5]);
        $this->companyCustomer->method('getMemberIds')->willReturn([300]);

        $this->sync->syncCompanyTree(4);

        self::assertSame([['ids' => [300], 'groupId' => 1]], $this->updates);
    }

    public function testClearingTheGroupSendsMembersStillInTheOldGroupToTheDefault(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => null]);
        $this->companyCustomer->method('getMemberIds')->with([1])->willReturn([100, 200]);
        // Only 100 is still in old group 5; 200 was moved to another group by hand.
        $this->inQueriedGroup = [100];

        $this->sync->syncCompanyTree(1, 5);

        self::assertSame([['ids' => [100], 'groupId' => 1]], $this->updates);
    }

    public function testWithoutAPreviousGroupMembersOfAnUngroupedCompanyAreLeftAlone(): void
    {
        $this->companyResource->method('getSubtree')->willReturn([
            1 => ['path' => '1', 'status' => CompanyInterface::STATUS_ACTIVE],
        ]);
        $this->settingResolver->method('resolveCustomerGroupIds')->willReturn([1 => null]);
        $this->companyCustomer->expects(self::never())->method('getMemberIds');

        $this->sync->syncCompanyTree(1, null);

        self::assertSame([], $this->updates);
    }
}
