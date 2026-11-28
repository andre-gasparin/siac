<?php

namespace App\Features\Reports\Http\Controllers;

use App\Features\Reports\Http\Requests\ReportCommentTemplateRequest;
use App\Http\Controllers\Controller;
use App\Models\ReportCommentTemplate;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCommentTemplateController extends Controller
{
    public function index(Request $request, Team $current_team): JsonResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $templates = ReportCommentTemplate::query()
            ->orderBy('body')
            ->orderBy('id')
            ->get()
            ->map(fn (ReportCommentTemplate $template): array => $this->serialize($template))
            ->values();

        return response()->json($templates);
    }

    public function store(ReportCommentTemplateRequest $request, Team $current_team): JsonResponse
    {
        $template = ReportCommentTemplate::query()->create([
            'created_by' => $request->user()?->id,
            'body' => trim((string) $request->validated('body')),
        ]);

        return response()->json($this->serialize($template), 201);
    }

    public function update(
        ReportCommentTemplateRequest $request,
        Team $current_team,
        ReportCommentTemplate $comment_template,
    ): JsonResponse {
        $comment_template->update([
            'body' => trim((string) $request->validated('body')),
        ]);

        return response()->json($this->serialize($comment_template->refresh()));
    }

    public function destroy(
        Request $request,
        Team $current_team,
        ReportCommentTemplate $comment_template,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $comment_template->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{id: int, body: string, created_at: string|null, updated_at: string|null}
     */
    private function serialize(ReportCommentTemplate $template): array
    {
        return [
            'id' => $template->id,
            'body' => $template->body,
            'created_at' => $template->created_at?->utc()->toIso8601String(),
            'updated_at' => $template->updated_at?->utc()->toIso8601String(),
        ];
    }
}
