import { randomUUID } from 'node:crypto';
import { seedCostRecord, seedOrganisation } from './finance';
import { seedPhp } from './seed';

export const unique = (prefix = 'Org'): string => `${prefix} ${randomUUID().slice(0, 8)}`;

export interface Prepared {
    organisation: number;
    canvas: number;
}

export function seedCanvasFor(organisation: number): number {
    return Number(/ID=(\d+)/.exec(seedPhp(`$c = App\\Models\\Canvas::factory()->create(['organisation_id' => ${organisation}]); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); echo 'ID=' . $c->id;`))?.[1]);
}

export function seedWorkspace(): Prepared {
    const organisation = seedOrganisation(unique(), [2026, 2027]);
    seedPhp(`App\\Models\\Owner::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Afdeling ICT']); App\\Models\\Application::factory()->create(['organisation_id' => ${organisation}, 'name' => 'Planningssysteem']);`);
    seedCostRecord(organisation, 'Licentie', { budgetCents: 12345, year: 2026 });

    return { organisation, canvas: seedCanvasFor(organisation) };
}
