<?php

namespace App\Features\Tasks\Http\Controllers;

use App\Features\Tasks\Events\TaskSavedEvent;
use App\Features\Tasks\Http\Requests\StoreAttachmentRequest;
use App\Features\Tasks\Services\TaskBoardService;
use App\Http\Controllers\Controller;
use App\Models\KanbanBoard;
use App\Models\KanbanTask;
use App\Models\KanbanTaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(
        protected TaskBoardService $service
    ) {}

    /**
     * Store a file attachment.
     */
    public function store(StoreAttachmentRequest $request, KanbanBoard $board, KanbanTask $task): RedirectResponse|JsonResponse
    {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        $sizeBytes = $file->getSize();

        $path = $file->store("kanban_attachments/{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'original_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        $formattedTask = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formattedTask, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'attachment' => $attachment,
                'task' => $formattedTask,
            ], 201);
        }

        return back()->with('success', 'Anexo enviado com sucesso.');
    }

    /**
     * Download an attachment privately.
     */
    public function download(Request $request, KanbanBoard $board, KanbanTask $task, KanbanTaskAttachment $attachment): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless($attachment->kanban_task_id === $task->id, 404);

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, KanbanBoard $board, KanbanTask $task, KanbanTaskAttachment $attachment): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless($attachment->kanban_task_id === $task->id, 404);

        if (Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $attachment->delete();

        $formattedTask = $this->service->formatTask($task->fresh());

        broadcast(new TaskSavedEvent($board->id, $formattedTask, isNew: false))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $formattedTask,
            ]);
        }

        return back()->with('success', 'Anexo removido.');
    }
}
