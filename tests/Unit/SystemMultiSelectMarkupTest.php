<?php

test('system multi-select does not nest interactive buttons', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/features/data-table/components/SystemMultiSelect.vue',
    );

    expect($component)
        ->not->toBeFalse()
        ->not->toMatch('/<button\b[^>]*>(?:(?!<\/button>).)*<button\b/is');
});
