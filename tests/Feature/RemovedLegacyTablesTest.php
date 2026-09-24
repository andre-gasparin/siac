<?php

use Illuminate\Support\Facades\Schema;

test('unused legacy tables and upload reference are removed', function () {
    $removedTables = [
        'upload_batches',
        'upload_import_issues',
        'legacy_import_mappings',
        'report_item_assets',
        'hidden_parameters',
        'parameter_group_parameter',
        'parameter_groups',
    ];

    foreach ($removedTables as $removedTable) {
        expect(Schema::hasTable($removedTable))->toBeFalse();
    }

    expect(Schema::hasColumn('parameter_values', 'upload_batch_id'))->toBeFalse();
});
