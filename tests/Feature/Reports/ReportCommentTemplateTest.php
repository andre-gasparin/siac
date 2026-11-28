<?php

use App\Models\ReportCommentTemplate;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);

    $this->team = Team::factory()->create();
    $this->otherTeam = Team::factory()->create();

    $this->team->members()->attach([$this->admin->id, $this->regularUser->id], ['role' => 'admin']);
    $this->otherTeam->members()->attach([$this->admin->id], ['role' => 'admin']);
});

test('admin can list global comment templates across teams', function () {
    $second = ReportCommentTemplate::factory()->create([
        'created_by' => $this->admin->id,
        'body' => 'Valores dentro dos limites estabelecidos.',
    ]);

    $first = ReportCommentTemplate::factory()->create([
        'created_by' => $this->admin->id,
        'body' => 'Apresentou elevação pontual no período.',
    ]);

    $responseTeamA = $this->actingAs($this->admin)
        ->getJson(route('reports.comment-templates.index', ['current_team' => $this->team]));

    $responseTeamA->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $first->id)
        ->assertJsonPath('0.body', 'Apresentou elevação pontual no período.')
        ->assertJsonPath('1.id', $second->id)
        ->assertJsonPath('1.body', 'Valores dentro dos limites estabelecidos.');

    $responseTeamB = $this->actingAs($this->admin)
        ->getJson(route('reports.comment-templates.index', ['current_team' => $this->otherTeam]));

    $responseTeamB->assertOk()
        ->assertJsonCount(2);
});

test('admin can store a new global comment template', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('reports.comment-templates.store', ['current_team' => $this->team]), [
            'body' => '  Necessário ajuste de dosagem do produto químico.  ',
        ]);

    $response->assertCreated()
        ->assertJsonPath('body', 'Necessário ajuste de dosagem do produto químico.');

    $this->assertDatabaseHas('report_comment_templates', [
        'created_by' => $this->admin->id,
        'body' => 'Necessário ajuste de dosagem do produto químico.',
    ]);
});

test('admin can update an existing comment template', function () {
    $template = ReportCommentTemplate::factory()->create([
        'created_by' => $this->admin->id,
        'body' => 'Texto antigo',
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson(route('reports.comment-templates.update', [
            'current_team' => $this->team,
            'comment_template' => $template->id,
        ]), [
            'body' => 'Texto atualizado para o relatório.',
        ]);

    $response->assertOk()
        ->assertJsonPath('id', $template->id)
        ->assertJsonPath('body', 'Texto atualizado para o relatório.');

    $this->assertDatabaseHas('report_comment_templates', [
        'id' => $template->id,
        'body' => 'Texto atualizado para o relatório.',
    ]);
});

test('admin can delete a comment template', function () {
    $template = ReportCommentTemplate::factory()->create([
        'created_by' => $this->admin->id,
        'body' => 'Comentário temporário',
    ]);

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('reports.comment-templates.destroy', [
            'current_team' => $this->team,
            'comment_template' => $template->id,
        ]));

    $response->assertNoContent();

    $this->assertDatabaseMissing('report_comment_templates', [
        'id' => $template->id,
    ]);
});

test('comment template body cannot be empty or whitespace only', function (string $invalidBody) {
    $this->actingAs($this->admin)
        ->postJson(route('reports.comment-templates.store', ['current_team' => $this->team]), [
            'body' => $invalidBody,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'newlines' => "\n\t  ",
]);

test('non-admin user cannot access comment templates endpoints', function () {
    $template = ReportCommentTemplate::factory()->create([
        'created_by' => $this->admin->id,
        'body' => 'Comentário restrito',
    ]);

    $this->actingAs($this->regularUser)
        ->getJson(route('reports.comment-templates.index', ['current_team' => $this->team]))
        ->assertForbidden();

    $this->actingAs($this->regularUser)
        ->postJson(route('reports.comment-templates.store', ['current_team' => $this->team]), [
            'body' => 'Tentativa não autorizada',
        ])
        ->assertForbidden();

    $this->actingAs($this->regularUser)
        ->putJson(route('reports.comment-templates.update', [
            'current_team' => $this->team,
            'comment_template' => $template->id,
        ]), [
            'body' => 'Tentativa de edição',
        ])
        ->assertForbidden();

    $this->actingAs($this->regularUser)
        ->deleteJson(route('reports.comment-templates.destroy', [
            'current_team' => $this->team,
            'comment_template' => $template->id,
        ]))
        ->assertForbidden();
});
