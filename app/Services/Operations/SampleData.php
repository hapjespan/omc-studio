<?php

namespace App\Services\Operations;

use App\Enums\ElementKind;
use App\Models\Application;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Allocation\AllocationSets;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\OrganisationService;
use App\Services\Finance\CostRecords;
use App\Services\Finance\FiscalYears;
use App\Services\Finance\OrganisationLists;
use App\Services\Finance\YearlyFigures;
use App\Services\Structure\ElementTree;
use Illuminate\Support\Facades\DB;

class SampleData
{
    public const REMOVED_KEY = 'sample_data_removed';

    private const OWNERS = ['owner_board' => null, 'owner_operations' => null, 'owner_information' => null];

    private const APPLICATIONS = ['application_office' => 'supplier_managed_services', 'application_accounting' => 'supplier_software_house'];

    private const STATEMENTS = [
        ['mission', null, 'mission'],
        ['vision', null, 'vision'],
        ['goal', 'goal_growth_title', 'goal_growth'],
        ['goal', 'goal_quality_title', 'goal_quality'],
    ];

    private const STRUCTURE = [
        'steering' => [
            ['name' => 'domain_strategy', 'activities' => ['activity_set_course' => 'adaptive', 'activity_steer_portfolio' => 'common']],
            ['name' => 'domain_risk', 'activities' => ['activity_weigh_risk' => 'specialized']],
        ],
        'governing' => [
            ['name' => 'domain_control', 'activities' => ['activity_annual_plan' => 'common']],
            ['name' => 'domain_quality', 'activities' => ['activity_audits' => 'specialized']],
        ],
        'primary_activities' => [
            [
                'name' => 'domain_services',
                'activities' => ['activity_quotations' => 'adaptive'],
                'clusters' => ['cluster_customer_contact' => ['activity_answer_questions' => 'common', 'activity_handle_complaints' => 'common']],
            ],
            ['name' => 'domain_delivery', 'activities' => ['activity_deliver_orders' => 'distinct']],
        ],
        'supporting_activities' => [
            ['name' => 'domain_internal_operations', 'activities' => ['activity_manage_staff' => 'common', 'activity_purchasing' => 'common']],
            ['name' => 'domain_information', 'activities' => ['activity_manage_workplaces' => 'common', 'activity_manage_applications' => 'specialized']],
        ],
    ];

    private const LISTS = [
        'actors' => [
            'group_customers' => ['item_business_customer', 'item_private_customer'],
            'group_partners' => ['item_supplier', 'item_adviser'],
        ],
        'channels' => [
            'group_digital' => ['item_website', 'item_portal'],
            'group_personal' => ['item_phone', 'item_desk'],
        ],
        'value_propositions' => ['group_promises' => ['item_fast_delivery', 'item_personal_advice']],
    ];

    private const COST_RECORDS = [
        ['code' => 'K-001', 'name' => 'cost_workplace_management', 'kind' => 'supplier_contract', 'supplier' => 'supplier_managed_services', 'owner' => 'owner_information', 'application' => 'application_office', 'budget' => '120.000,00', 'forecast' => '118.450,25', 'run' => '80', 'change' => '20', 'allocation' => ['activity_manage_workplaces' => 10000]],
        ['code' => 'K-002', 'name' => 'cost_application_management', 'kind' => 'supplier_contract', 'supplier' => 'supplier_software_house', 'owner' => 'owner_information', 'application' => 'application_accounting', 'budget' => '84.500,33', 'forecast' => '84.500,33', 'run' => '70', 'change' => '30', 'allocation' => ['activity_manage_applications' => 6000, 'activity_manage_workplaces' => 4000]],
        ['code' => 'K-003', 'name' => 'cost_customer_service', 'kind' => 'personnel', 'supplier' => null, 'owner' => 'owner_operations', 'application' => null, 'budget' => '310.000,01', 'forecast' => '305.750,99', 'run' => '90', 'change' => '10', 'allocation' => ['activity_answer_questions' => 3334, 'activity_handle_complaints' => 3333, 'activity_quotations' => 3333]],
        ['code' => 'K-004', 'name' => 'cost_order_handling', 'kind' => 'personnel', 'supplier' => null, 'owner' => 'owner_operations', 'application' => null, 'budget' => '175.250,75', 'forecast' => '180.000,00', 'run' => '85', 'change' => '15', 'allocation' => ['activity_deliver_orders' => 10000]],
        ['code' => 'K-005', 'name' => 'cost_annual_planning', 'kind' => 'project', 'supplier' => 'supplier_consultancy', 'owner' => 'owner_board', 'application' => null, 'budget' => '42.000,50', 'forecast' => '41.000,00', 'run' => '40', 'change' => '60', 'allocation' => ['activity_annual_plan' => 5000, 'activity_set_course' => 5000]],
        ['code' => 'K-006', 'name' => 'cost_audits', 'kind' => 'supplier_contract', 'supplier' => 'supplier_auditor', 'owner' => 'owner_board', 'application' => null, 'budget' => '28.900,10', 'forecast' => '29.400,10', 'run' => '75', 'change' => '25', 'allocation' => ['activity_audits' => 7500, 'activity_weigh_risk' => 2500]],
        ['code' => 'K-007', 'name' => 'cost_human_resources', 'kind' => 'personnel', 'supplier' => null, 'owner' => 'owner_operations', 'application' => null, 'budget' => '96.333,33', 'forecast' => '96.333,33', 'run' => '95', 'change' => '5', 'allocation' => ['activity_manage_staff' => 10000]],
        ['code' => 'K-008', 'name' => 'cost_procurement', 'kind' => 'other', 'supplier' => 'supplier_procurement', 'owner' => 'owner_operations', 'application' => null, 'budget' => '53.777,77', 'forecast' => '52.000,00', 'run' => '60', 'change' => '40', 'allocation' => ['activity_purchasing' => 5000, 'activity_steer_portfolio' => 2500, 'domain_delivery' => 2500]],
        ['code' => 'K-009', 'name' => 'cost_training', 'kind' => 'other', 'supplier' => null, 'owner' => 'owner_operations', 'application' => null, 'budget' => '18.200,45', 'forecast' => '17.800,45', 'run' => '20', 'change' => '80', 'allocation' => []],
        ['code' => 'K-010', 'name' => 'cost_innovation', 'kind' => 'project', 'supplier' => null, 'owner' => 'owner_board', 'application' => null, 'budget' => '65.000,99', 'forecast' => '60.000,00', 'run' => '10', 'change' => '90', 'allocation' => []],
    ];

    public function __construct(
        private readonly OrganisationService $organisations,
        private readonly OrganisationLists $lists,
        private readonly FiscalYears $fiscalYears,
        private readonly CanvasLifecycle $canvases,
        private readonly ElementTree $tree,
        private readonly CostRecords $costRecords,
        private readonly YearlyFigures $figures,
        private readonly AllocationSets $allocations,
    ) {}

    public function install(): void
    {
        if ($this->removed() || Organisation::where('is_sample', true)->exists()) {
            return;
        }

        $organisation = $this->organisations->create($this->text('organisation_name'));
        $organisation->update(['is_sample' => true]);

        $template = Canvas::where('is_blank_template', true)->firstOrFail();
        $canvas = $this->canvases->createFromTemplate($organisation, $template, $this->text('canvas_name'));

        $this->statements($canvas);
        $this->costs($organisation, $canvas, $this->fiscalYears->add($organisation, (int) now()->year), $this->structure($canvas));
    }

    public function remove(User $user): void
    {
        $organisation = Organisation::where('is_sample', true)->first();

        DB::transaction(function () use ($organisation, $user) {
            if ($organisation !== null) {
                Canvas::where('organisation_id', $organisation->getKey())->each(fn (Canvas $canvas) => $this->canvases->delete($canvas, $canvas->name));
                $deleted = $this->costRecords->deleteMany($organisation, CostRecord::where('organisation_id', $organisation->getKey())->get(), $user);
                UndoRecord::whereKey($deleted['token'])->delete();
                $this->organisations->delete($organisation);
            }

            DB::table('application_state')->updateOrInsert(['key' => self::REMOVED_KEY], ['value' => '1']);
        });
    }

    public function sample(): ?Organisation
    {
        return Organisation::where('is_sample', true)->first();
    }

    private function removed(): bool
    {
        return DB::table('application_state')->where('key', self::REMOVED_KEY)->exists();
    }

    private function text(string $key): string
    {
        return (string) __('sample.'.$key);
    }

    private function statements(Canvas $canvas): void
    {
        $position = 0;

        foreach (self::STATEMENTS as [$kind, $title, $body]) {
            Statement::create([
                'canvas_id' => $canvas->getKey(),
                'kind' => $kind,
                'title' => $title === null ? null : $this->text($title),
                'body' => $this->text($body),
                'position' => ++$position,
            ]);
        }
    }

    /** @return array<string, string> */
    private function structure(Canvas $canvas): array
    {
        $areas = Area::all()->keyBy('key');
        $modalities = Modality::pluck('id', 'key')->all();
        $targets = [];

        foreach (self::STRUCTURE as $areaKey => $domains) {
            foreach ($domains as $domain) {
                $parent = $this->element($canvas, $areas[$areaKey], ElementKind::Domain, null, $domain['name']);
                $targets[$domain['name']] = $parent;
                $targets += $this->activities($canvas, $areas[$areaKey], $parent, $domain['activities'], $modalities);

                foreach ($domain['clusters'] ?? [] as $clusterKey => $activities) {
                    $cluster = $this->element($canvas, $areas[$areaKey], ElementKind::Cluster, $parent, $clusterKey);
                    $targets[$clusterKey] = $cluster;
                    $targets += $this->activities($canvas, $areas[$areaKey], $cluster, $activities, $modalities);
                }
            }
        }

        foreach (self::LISTS as $areaKey => $groups) {
            foreach ($groups as $groupKey => $items) {
                $group = $this->element($canvas, $areas[$areaKey], ElementKind::Domain, null, $groupKey);

                foreach ($items as $item) {
                    $this->element($canvas, $areas[$areaKey], ElementKind::Activity, $group, $item);
                }
            }
        }

        return $targets;
    }

    /**
     * @param  array<string, string>  $activities
     * @param  array<string, int>  $modalities
     * @return array<string, string>
     */
    private function activities(Canvas $canvas, Area $area, string $parent, array $activities, array $modalities): array
    {
        $created = [];

        foreach ($activities as $key => $modalityKey) {
            $created[$key] = $this->element($canvas, $area, ElementKind::Activity, $parent, $key, $modalities[$modalityKey]);
        }

        return $created;
    }

    private function element(Canvas $canvas, Area $area, ElementKind $kind, ?string $parent, string $key, ?int $modalityId = null): string
    {
        return (string) $this->tree->create($canvas, $area, $kind, $parent, ['name' => $this->text($key), 'modality_id' => $modalityId])->getKey();
    }

    /** @param array<string, string> $targets */
    private function costs(Organisation $organisation, Canvas $canvas, FiscalYear $year, array $targets): void
    {
        $owners = $this->entries($organisation, Owner::class, self::OWNERS);
        $applications = $this->entries($organisation, Application::class, self::APPLICATIONS);

        foreach (self::COST_RECORDS as $row) {
            $record = $this->costRecords->create($organisation, [
                'code' => $row['code'],
                'name' => $this->text($row['name']),
                'cost_kind' => $row['kind'],
                'supplier' => $row['supplier'] === null ? null : $this->text($row['supplier']),
                'owner_id' => $owners[$row['owner']],
                'application_id' => $row['application'] === null ? null : $applications[$row['application']],
            ]);

            $record->setRelation('organisation', $organisation);
            $this->figure($record, $year, $row);

            $lines = [];

            foreach ($row['allocation'] as $key => $basisPoints) {
                $lines[] = ['element_id' => $targets[$key], 'basis_points' => $basisPoints];
            }

            if ($lines !== []) {
                $this->allocations->replace($canvas, $record, $year, $lines);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function figure(CostRecord $record, FiscalYear $year, array $row): void
    {
        $this->figures->save($record, $year, [
            'budget' => $row['budget'],
            'forecast' => $row['forecast'],
            'run' => $row['run'],
            'change' => $row['change'],
        ]);
    }

    /**
     * @param  class-string<Application|Owner>  $model
     * @param  array<string, string|null>  $names
     * @return array<string, int>
     */
    private function entries(Organisation $organisation, string $model, array $names): array
    {
        $entries = [];

        foreach ($names as $key => $supplier) {
            $data = ['name' => $this->text($key)] + ($supplier === null ? [] : ['supplier' => $this->text($supplier)]);
            $entries[$key] = (int) $this->lists->create($organisation, $model, $data)->getKey();
        }

        return $entries;
    }
}
