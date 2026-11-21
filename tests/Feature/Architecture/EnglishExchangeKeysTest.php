<?php

namespace Tests\Feature\Architecture;

use App\Enums\CostKind;
use App\Services\Exchange\AllocationCsv;
use App\Services\Exchange\CostRecordImport;
use App\Services\Exchange\CostRecordRowValidator;
use App\Services\Exchange\Headers;
use App\Services\Exchange\StructureCsv;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class EnglishExchangeKeysTest extends TestCase
{
    public function test_internal_column_keys_are_english(): void
    {
        $this->assertSame(['area', 'domain', 'cluster', 'activity', 'description', 'modality'], StructureCsv::COLUMNS);
        $this->assertSame(['cost_record_code', 'year', 'target_path', 'percentage'], AllocationCsv::COLUMNS);
        $this->assertSame(['code', 'name', 'kind', 'supplier', 'owner', 'cost_centre', 'application', 'year', 'budget', 'forecast', 'run_percentage', 'change_percentage', 'fte'], CostRecordImport::COLUMNS);
    }

    public function test_the_header_texts_come_from_the_translation_file_unchanged(): void
    {
        $this->assertSame(['area', 'domein', 'cluster', 'activiteit', 'omschrijving', 'modaliteit'], Headers::row('structure', StructureCsv::COLUMNS));
        $this->assertSame(['kostenpost_code', 'jaar', 'doel_pad', 'percentage'], Headers::row('allocation', AllocationCsv::COLUMNS));
        $this->assertSame(['code', 'naam', 'soort', 'leverancier', 'eigenaar', 'kostenplaats', 'applicatie', 'jaar', 'budget', 'prognose', 'run_pct', 'change_pct', 'fte'], Headers::row('cost_record', CostRecordImport::COLUMNS));
        $this->assertSame(['leverancierscontract', 'personeel', 'project', 'overig'], array_keys(CostRecordRowValidator::kinds()));
    }

    public function test_the_exchange_cost_kind_labels_equal_the_screen_labels(): void
    {
        foreach (CostKind::cases() as $kind) {
            $this->assertSame(__('cost_kind.'.$kind->value), Headers::label('cost_kind', $kind->value));
        }
    }

    public function test_no_invoeren_identifier_remains_in_app_and_scripts(): void
    {
        $found = [];

        foreach (['app', 'resources/js'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (in_array($file->getExtension(), ['php', 'vue', 'ts'], true) && stripos((string) file_get_contents($file->getPathname()), 'invoeren') !== false) {
                    $found[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $found);
        $this->assertArrayNotHasKey('drawing.invoeren_note_before', json_decode((string) file_get_contents(base_path('lang/nl.json')), true));
    }
}
