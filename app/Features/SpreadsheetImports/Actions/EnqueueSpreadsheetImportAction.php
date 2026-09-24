<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Models\SpreadsheetEmailInboxItem;
use App\Models\SpreadsheetImportQueue;
use App\Models\SpreadsheetTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnqueueSpreadsheetImportAction
{
    /**
     * Enqueue a confirmed email inbox item for background processing.
     */
    public function enqueueFromInboxItem(
        SpreadsheetEmailInboxItem $inboxItem,
        User $user,
        ?int $templateId = null,
        ?string $referenceDate = null,
    ): SpreadsheetImportQueue {
        return DB::transaction(function () use ($inboxItem, $user, $templateId, $referenceDate) {
            $effectiveTemplateId = $templateId ? (int) $templateId : $inboxItem->spreadsheet_template_id;
            $effectiveDate = $referenceDate ?? ($inboxItem->extracted_reference_date?->format('Y-m-d'));

            $inboxItem->update([
                'spreadsheet_template_id' => $effectiveTemplateId,
                'extracted_reference_date' => $effectiveDate,
                'status' => 'queued',
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            return SpreadsheetImportQueue::create([
                'team_id' => $inboxItem->team_id,
                'user_id' => $user->id,
                'spreadsheet_template_id' => $effectiveTemplateId,
                'source_type' => 'email',
                'spreadsheet_email_inbox_item_id' => $inboxItem->id,
                'file_name' => $inboxItem->file_name,
                'file_path' => $inboxItem->file_path,
                'reference_date' => $effectiveDate,
                'status' => 'pending',
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Enqueue a manual file upload for background processing.
     */
    public function enqueueFromManualUpload(
        Team $team,
        User $user,
        SpreadsheetTemplate $template,
        UploadedFile $file,
        ?string $referenceDate = null,
    ): SpreadsheetImportQueue {
        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');
        $baseFolder = date('Y/m/d').'/'.$team->slug;

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension();
        $storedName = date('Ymd_His').'_'.Str::random(8)."_{$safeName}.{$ext}";
        $relativePath = "{$baseFolder}/{$storedName}";

        $file->storeAs($baseFolder, $storedName, $storageDisk);

        return SpreadsheetImportQueue::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'spreadsheet_template_id' => $template->id,
            'source_type' => 'manual_upload',
            'spreadsheet_email_inbox_item_id' => null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
            'reference_date' => $referenceDate,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }
}
