<?php

use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $user = User::factory()->create(['current_team_id' => $this->team->id]);
    $this->team->members()->attach($user, ['role' => 'admin']);
    $this->actingAs($user);
});

test('the header theme shortcut switches and remembers the appearance', function () {
    $shortcut = 'header button[aria-label="Tema escuro"]';

    visit(route('dashboard', $this->team))->inLightMode()
        ->assertAriaAttribute($shortcut, 'pressed', 'false')
        ->click($shortcut)
        ->assertAriaAttribute($shortcut, 'pressed', 'true')
        ->assertScript('document.documentElement.classList.contains("dark")')
        ->refresh()
        ->assertAriaAttribute($shortcut, 'pressed', 'true')
        ->click($shortcut)
        ->assertAriaAttribute($shortcut, 'pressed', 'false')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoJavaScriptErrors();
});

test('the sidebar swaps the full logo for the favicon when collapsed', function () {
    $logo = 'a[aria-label="Consucal"] img[src="/logo.png"]';
    $favicon = 'a[aria-label="Consucal"] img[src="/favicon.png"]';
    $toggle = '[data-sidebar="trigger"]';

    visit(route('dashboard', $this->team))->on()->desktop()
        ->assertVisible($logo)
        ->assertMissing($favicon)
        ->click($toggle)
        ->assertMissing($logo)
        ->assertVisible($favicon)
        ->click($toggle)
        ->assertVisible($logo)
        ->assertMissing($favicon);
});
