<?php

namespace Database\Seeders;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Application;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Database\Seeder;

class DesignComparisonSeeder extends Seeder
{
    public const EMAIL = 'beheerder@voorbeeld.test';

    public const PASSWORD = 'Voorbeeld-wachtwoord-1';

    private const STRUCTURE = [
        'steering' => [['Strategie', []], ['Innovatie', []]],
        'governing' => [['Financiële sturing', []], ['Kwaliteit & naleving', []]],
        'primary_activities' => [
            ['Plaatsing & planning', ['Aanmelden en plaatsen', 'Wachtlijst beheren', 'Roosters maken', 'Contracten opstellen', 'Wijzigingen verwerken']],
            ['Opvang & onderwijs', ['Kind volgen', 'Dagprogramma uitvoeren', 'Onderwijs geven', 'Overdracht opvang en school']],
            ['Ouders & communicatie', ['Ouders informeren', 'Vragen afhandelen', 'Facturen toelichten']],
            ['Kwaliteit & zorg', ['Zorgsignalen opvolgen']],
        ],
        'supporting_activities' => [
            ['Personeel', ['Werven en inwerken', 'Salaris verwerken', 'Verzuim begeleiden']],
            ['Financiën', ['Financiële administratie voeren', 'Factureren en incasseren']],
            ['ICT', [['Werkplek & beheer', ['Werkplekken beheren', 'Gebruikers ondersteunen']], ['Applicaties', ['Applicaties beheren', 'Koppelingen onderhouden']]]],
            ['Huisvesting', []],
        ],
        'actors' => [['Klanten', ['Ouders', 'Kinderen', 'Scholen']], ['Partners', ['Gemeente', 'Jeugdzorg']], ['Toezicht', ['GGD', 'Onderwijsinspectie']]],
        'channels' => [['Digitaal', ['Ouderportaal', 'Website', 'E-mail']], ['Fysiek', ['Locatie', 'Telefoon']]],
        'value_propositions' => [['Opvang', ['Dagopvang', 'Buitenschoolse opvang', 'Peuteropvang']], ['Onderwijs', ['Primair onderwijs']]],
    ];

    private const COSTS = [
        ['KP-001', 'Kindplanning-software', 'supplier_contract', 'Bedrijfsvoering', 'Kindplanning', 8400000, 8600000, ['Plaatsing & planning' => 10000]],
        ['KP-002', 'Ouderportaal en app', 'supplier_contract', 'Communicatie', 'Ouderportaal', 3600000, 3600000, ['Ouders & communicatie' => 10000]],
        ['KP-003', 'HR- en salarissysteem', 'supplier_contract', 'HR', 'HR-systeem', 5800000, 5800000, ['Personeel' => 10000]],
        ['KP-004', 'Financieel pakket', 'supplier_contract', 'Financiën', 'Financieel pakket', 4200000, 4200000, ['Financiën' => 7000, 'Financiële sturing' => 3000]],
        ['KP-005', 'Werkplekbeheer en netwerk', 'supplier_contract', 'ICT', null, 12000000, 12600000, ['ICT' => 10000]],
        ['KP-006', 'Team ICT', 'personnel', 'ICT', null, 21000000, 20400000, ['ICT' => 6000, 'Strategie' => 1000, 'Plaatsing & planning' => 1500, 'Opvang & onderwijs' => 1500]],
        ['KP-007', 'Leerlingadministratie', 'supplier_contract', 'Onderwijs', 'Leerlingadministratie', 4800000, 4800000, ['Opvang & onderwijs' => 10000]],
        ['KP-008', 'Nieuwe website', 'project', 'Communicatie', 'Website', 6500000, 7100000, ['Ouders & communicatie' => 10000]],
        ['KP-009', 'Telefonie', 'supplier_contract', 'ICT', null, 2700000, 2700000, []],
        ['KP-010', 'Licenties kantoorsoftware', 'supplier_contract', 'ICT', 'Kantoorsoftware', 5400000, 5400000, []],
    ];

    public function run(): void
    {
        app(ReferenceDataStep::class)();

        $organisation = Organisation::factory()->create(['name' => 'Voorbeeld BV']);
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Voorbeeldcanvas']);
        UnknownAllocationDomain::ensure($canvas);
        $year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);

        $targets = $this->structure($canvas);
        $this->costs($organisation, $canvas, $year, $targets);
        $this->statements($canvas);

        User::factory()->administrator()->create(['name' => 'Beheerder', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
        User::factory()->create(['name' => 'Mila de Vries', 'email' => 'mila@voorbeeld.test', 'password' => self::PASSWORD]);
    }

    /**
     * @return array<string, string>
     */
    private function structure(Canvas $canvas): array
    {
        $targets = [];

        foreach (self::STRUCTURE as $areaKey => $domains) {
            $area = Area::where('key', $areaKey)->firstOrFail();

            foreach ($domains as $position => [$name, $children]) {
                $domain = $this->element($canvas, $area, 'domain', $name, null, $position);
                $targets[$name] = (string) $domain->id;

                foreach ($children as $index => $child) {
                    if (is_array($child)) {
                        $cluster = $this->element($canvas, $area, 'cluster', $child[0], $domain, $index);
                        $targets[$child[0]] = (string) $cluster->id;

                        foreach ($child[1] as $at => $activity) {
                            $this->element($canvas, $area, 'activity', $activity, $cluster, $at);
                        }

                        continue;
                    }

                    $this->element($canvas, $area, 'activity', $child, $domain, $index);
                }
            }
        }

        return $targets;
    }

    private function element(Canvas $canvas, Area $area, string $kind, string $name, ?Element $parent, int $position): Element
    {
        return Element::factory()->create([
            'canvas_id' => $canvas->id,
            'area_id' => $area->id,
            'kind' => $kind,
            'name' => $name,
            'parent_id' => $parent?->id,
            'position' => $position,
        ]);
    }

    /**
     * @param  array<string, string>  $targets
     */
    private function costs(Organisation $organisation, Canvas $canvas, FiscalYear $year, array $targets): void
    {
        $owners = [];
        $applications = [];

        foreach (self::COSTS as [$code, $name, $kind, $ownerName, $applicationName, $budget, $forecast, $allocation]) {
            $owners[$ownerName] ??= (int) Owner::factory()->create(['organisation_id' => $organisation->id, 'name' => $ownerName])->id;

            if ($applicationName !== null) {
                $applications[$applicationName] ??= (int) Application::factory()->create(['organisation_id' => $organisation->id, 'name' => $applicationName])->id;
            }

            $record = CostRecord::factory()->create([
                'organisation_id' => $organisation->id,
                'code' => $code,
                'name' => $name,
                'cost_kind' => $kind,
                'owner_id' => $owners[$ownerName],
                'application_id' => $applications[(string) $applicationName] ?? null,
            ]);

            YearlyFigure::factory()->create([
                'cost_record_id' => $record->id,
                'fiscal_year_id' => $year->id,
                'budget_cents' => $budget,
                'forecast_cents' => $forecast,
                'run_bp' => 7000,
                'change_bp' => 3000,
                'fte_hundredths' => 280,
                'reference_date' => '2026-11-01',
            ]);

            if ($allocation === []) {
                continue;
            }

            $set = AllocationSet::factory()->create(['canvas_id' => $canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $year->id]);
            $position = 0;

            foreach ($allocation as $target => $basisPoints) {
                AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $targets[$target], 'basis_points' => $basisPoints, 'position' => $position++]);
            }
        }
    }

    private function statements(Canvas $canvas): void
    {
        Statement::create(['canvas_id' => $canvas->id, 'kind' => 'mission', 'title' => '', 'body' => 'Elk kind een goede start, dicht bij huis.', 'position' => 0]);
        Statement::create(['canvas_id' => $canvas->id, 'kind' => 'vision', 'title' => '', 'body' => 'Opvang en onderwijs als één doorlopende lijn.', 'position' => 0]);

        foreach ([['Eén kindprofiel.', 'Van opvang tot en met groep 8.'], ['Minder administratie.', 'Meer tijd voor de groep.'], ['Ouders digitaal geholpen.', 'Eén ingang voor alle vragen.']] as $index => [$title, $body]) {
            Statement::create(['canvas_id' => $canvas->id, 'kind' => 'goal', 'title' => $title, 'body' => $body, 'position' => $index]);
        }
    }
}
