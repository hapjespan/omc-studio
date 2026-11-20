# Delivery 1 refusals and their server tests

Every refusal below is sent straight at the server in a test, without the screens (FR-094). `tests/Feature/Security/D1RefusalCoverageTest.php` reads `lang/nl.json`, takes every `rule.*` message plus the list below, and fails when one has no server test that names its key or its Dutch text, or when one is missing from this file.

Refusals of a later delivery are not listed. A new `rule.*` message must get a test and a row here, or the coverage test fails.

## Refusal messages

| Key | Message (Dutch UI text) | Server test |
| --- | --- | --- |
| `canvas_lifecycle.name_required` | Naam: vul een naam in van 1 tot 150 tekens | D1RefusalMessagesTest |
| `direction_entry.title_required` | Titel: vul een titel in | DirectionStatementsTest |
| `direction_entry.title_too_long` | Titel: gebruik maximaal 150 tekens | D1RefusalMessagesTest |
| `direction_entry.too_long` | :label: gebruik maximaal 2000 tekens | DirectionStatementsTest |
| `list.invalid_filters` | Dit filter bestaat niet | D1RefusalMessagesTest |
| `list.invalid_query` | Deze zoekopdracht is niet geldig | D1RefusalMessagesTest |
| `list.invalid_sort` | Sorteren op deze kolom kan niet | D1RefusalMessagesTest |
| `modality_entry.list_area` | Een modaliteit en onderbouwing kunnen alleen bij elementen in een activiteitenarea | ModalityRulesTest |
| `modality_entry.rationale_needs_modality` | Een onderbouwing kan alleen bij een modaliteit | ModalityRulesTest |
| `organisations.delete_blocked` | Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst | OrganisationDeletionTest |
| `organisations.logo_invalid` | Logo: kies een PNG- of JPEG-afbeelding van maximaal 1 MB en 4000 bij 4000 pixels | LogoUploadTest |
| `organisations.name_required` | Naam: vul een naam in van 1 tot 150 tekens | D1RefusalMessagesTest |
| `organisations.name_taken` | Naam: er bestaat al een organisatie met deze naam | OrganisationControllerTest |
| `quick_entry.activity_parent` | onder een bestaande activiteit kunnen geen regels staan | QuickEntryParserTest |
| `quick_entry.duplicate` | deze naam staat al onder dezelfde regel erboven | QuickEntryParserTest |
| `quick_entry.indent_mismatch` | inspringing past niet bij de regel erboven | QuickEntryTest |
| `quick_entry.no_clusters` | in deze area bestaan geen clusters | QuickEntryParserTest |
| `quick_entry.too_long` | de naam is langer dan 150 tekens | QuickEntryParserTest |
| `rule.activity_parent` | Een activiteit hoort onder een domein of een cluster | CreateElementTest |
| `rule.blank_template_write` | Leeg canvas kan niet worden gewijzigd | WritableGuardTest |
| `rule.canvas_archived` | Dit canvas is gearchiveerd | ArchiveCanvasTest |
| `rule.canvas_confirmation_mismatch` | Typ de naam van het canvas precies zoals hij er staat | DeleteCanvasTest |
| `rule.canvas_name_taken` | Naam: dit canvas bestaat al in deze organisatie | CanvasRoutesTest |
| `rule.cluster_choice_required` | Kies wat er met de activiteiten van het cluster gebeurt | ClusterDeletionTest |
| `rule.cluster_in_cluster` | Een cluster kan niet in een ander cluster | RefusalMatrixTest |
| `rule.cluster_lift_taken` | Terugzetten kan niet: :names staat al onder het domein | ClusterDeletionTest |
| `rule.cluster_parent` | Een cluster hoort onder een domein | CreateElementTest |
| `rule.direction_no_elements` | In Koers staan geen elementen | CreateElementTest |
| `rule.domain_parent` | Een domein hoort direct onder een area | CreateElementTest |
| `rule.element_archived` | Een gearchiveerd element kan niet worden verplaatst | ReviewFixesTest |
| `rule.element_name_taken` | Naam: er staat al een element met deze naam op deze plek | ElementNamesTest |
| `rule.icon_list_only` | Een icoon kan alleen bij elementen in een lijstarea | UpdateElementTest |
| `rule.icon_unknown` | Dit icoon staat niet in de lijst | D1RefusalMessagesTest |
| `rule.move_other_kind` | Verplaatsen kan alleen naar een area van dezelfde soort | MoveElementTest |
| `rule.name_required` | Naam: vul een naam in | D1RefusalMessagesTest |
| `rule.no_clusters_in_area` | In deze area bestaan geen clusters | RefusalMatrixTest |
| `rule.organisation_archived` | Deze organisatie is gearchiveerd | ArchiveCanvasTest |
| `rule.restore_parent_inactive` | Terugzetten kan niet: het bovenliggende element is gearchiveerd | RefusalMatrixTest |
| `rule.template_write` | Een sjabloon bevat alleen structuur en Koers | WritableGuardTest |
| `rule.undo_parent_gone` | De rij waar dit onder hoorde bestaat niet meer | UndoStoreTest |
| `rule.undo_unavailable` | Ongedaan maken kan niet meer | UndoStoreTest |
| `rule.unknown_allocation_managed` | Onbekende allocatie wordt door het systeem beheerd | UnknownAllocationProtectionTest |
| `templates.name_taken` | Naam: dit sjabloon bestaat al | D1RefusalMessagesTest |
| `users.current_password_required` | Huidig wachtwoord: vul je huidige wachtwoord in om je e-mailadres te wijzigen | OwnEmailChangeTest |
| `users.email_invalid` | E-mailadres: vul een geldig e-mailadres in | D1RefusalMessagesTest |
| `users.email_taken` | E-mailadres: dit adres is al in gebruik | UserAdministrationTest |
| `users.last_administrator` | Er moet minstens één beheerder blijven | LastAdministratorTest |
| `users.name_required` | Naam: vul een naam in | D1RefusalMessagesTest |
| `users.role_invalid` | Rol: kies Administrator of Lezer | D1RefusalMessagesTest |
| `validation.name_forbidden_sign` | :label: het teken > is niet toegestaan | ElementNamesTest |
| `validation.text_max` | :label: gebruik hoogstens :max tekens | ReviewFixesTest |

## Refusals that are not messages

| Refusal | Server test |
| --- | --- |
| A Reader on any write route outside the account routes | `tests/Feature/Security/RouteEnumerationTest.php` (every write route carries the `administer` gate) |
| Writes to an archived Canvas, archived Organisation, Template or the blank Template | `WritableGuardTest`, `ArchiveCanvasTest`, `TemplateProtectionTest` |
| A Canvas, Element or Goal of another Canvas through a mismatched route | `ElementRoutesTest`, `CanvasRoutesTest` (scoped bindings answer 404) |
| Direction takes no Elements, Areas cannot be changed | `AreasImmutableTest`, `CreateElementTest` |
| Unknown allocation Domain cannot be changed or deleted | `UnknownAllocationProtectionTest` |
| Last Administrator cannot be deleted or demoted | `LastAdministratorTest` |
