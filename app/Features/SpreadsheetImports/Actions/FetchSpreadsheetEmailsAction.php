<?php

namespace App\Features\SpreadsheetImports\Actions;

use App\Features\SpreadsheetImports\Services\IncomingEmailAttachment;
use App\Features\SpreadsheetImports\Services\IncomingEmailMessage;
use App\Features\SpreadsheetImports\Services\SpreadsheetEmailReaderService;
use App\Models\SpreadsheetEmailInboxItem;
use App\Models\SpreadsheetEmailRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FetchSpreadsheetEmailsAction
{
    public function __construct(
        public SpreadsheetEmailReaderService $readerService,
        public ExtractSpreadsheetReferenceDateAction $dateExtractor,
    ) {}

    /**
     * Fetch unread emails, test against active rules, save attachments, and create inbox records.
     *
     * @return array{
     *     emails_checked: int,
     *     attachments_captured: int,
     *     messages: string[],
     * }
     */
    public function execute(): array
    {
        $rules = SpreadsheetEmailRule::query()
            ->where('is_active', true)
            ->with(['template', 'team'])
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        if ($rules->isEmpty()) {
            Log::info('[FetchSpreadsheetEmailsAction] Nenhuma regra ativa cadastrada. Leitura ignorada.');

            return [
                'emails_checked' => 0,
                'attachments_captured' => 0,
                'messages' => ['Nenhuma regra ativa cadastrada.'],
            ];
        }

        $emails = $this->readerService->fetchUnreadEmails();
        $emailsChecked = count($emails);
        $attachmentsCaptured = 0;
        $messages = [];

        $storageDisk = (string) config('spreadsheet_email.storage_disk', 'local');
        $baseFolder = (string) config('spreadsheet_email.storage_path', 'spreadsheet_inbox');

        foreach ($emails as $email) {
            $spreadsheetAttachments = $this->filterSpreadsheetAttachments($email->attachments);

            if (empty($spreadsheetAttachments)) {
                Log::info("[FetchSpreadsheetEmailsAction] E-mail '{$email->subject}' de {$email->senderEmail} sem anexos de planilha suportados.");

                continue;
            }

            // Find matching rule (AND logic for all specified conditions in the rule)
            $matchedRule = $this->findMatchingRule($email, $spreadsheetAttachments, $rules);

            if (! $matchedRule) {
                Log::info("[FetchSpreadsheetEmailsAction] E-mail '{$email->subject}' de {$email->senderEmail} não correspondeu a nenhuma regra ativa.");

                continue;
            }

            $teamSlug = $matchedRule->team?->slug ?? 'global';
            $baseFolder = date('Y/m/d')."/{$teamSlug}";

            foreach ($spreadsheetAttachments as $attachment) {
                // If the rule specifies an attachment name condition, ensure this attachment matches
                if (! empty($matchedRule->attachment_name_value)) {
                    if (! $this->matchCondition($attachment->filename, $matchedRule->attachment_name_operator ?? 'contains', $matchedRule->attachment_name_value)) {
                        continue;
                    }
                }

                $safeName = Str::slug(pathinfo($attachment->filename, PATHINFO_FILENAME));
                $ext = strtolower(pathinfo($attachment->filename, PATHINFO_EXTENSION));
                $storageFileName = date('Ymd_His').'_'.Str::random(8)."_{$safeName}.{$ext}";
                $storageRelativePath = "{$baseFolder}/{$storageFileName}";

                Storage::disk($storageDisk)->put($storageRelativePath, $attachment->content);
                $fullPath = Storage::disk($storageDisk)->path($storageRelativePath);

                $extractedDate = $this->dateExtractor->execute(
                    fileName: $attachment->filename,
                    subject: $email->subject,
                    body: $email->bodyText,
                    filePath: $fullPath,
                    customPattern: $matchedRule->date_extraction_pattern,
                );

                SpreadsheetEmailInboxItem::create([
                    'team_id' => $matchedRule->team_id,
                    'spreadsheet_email_rule_id' => $matchedRule->id,
                    'spreadsheet_template_id' => $matchedRule->spreadsheet_template_id,
                    'email_message_id' => $email->messageId,
                    'sender_email' => $email->senderEmail,
                    'sender_name' => $email->senderName,
                    'subject' => $email->subject,
                    'body_snippet' => Str::limit($email->bodyText ?? '', 500),
                    'email_received_at' => $email->date?->format('Y-m-d H:i:s'),
                    'file_name' => $attachment->filename,
                    'file_path' => $storageRelativePath,
                    'file_size_bytes' => $attachment->sizeBytes,
                    'extracted_reference_date' => $extractedDate,
                    'status' => 'pending_confirmation',
                ]);

                $attachmentsCaptured++;
                $messages[] = "Anexo '{$attachment->filename}' capturado e vinculado à regra '{$matchedRule->name}'.";
            }
        }

        return [
            'emails_checked' => $emailsChecked,
            'attachments_captured' => $attachmentsCaptured,
            'messages' => $messages,
        ];
    }

    /**
     * @param  IncomingEmailAttachment[]  $attachments
     * @return IncomingEmailAttachment[]
     */
    protected function filterSpreadsheetAttachments(array $attachments): array
    {
        $allowedExtensions = ['xlsx', 'xls', 'csv'];

        return array_values(array_filter($attachments, function (IncomingEmailAttachment $att) use ($allowedExtensions) {
            $ext = strtolower(pathinfo($att->filename, PATHINFO_EXTENSION));

            return in_array($ext, $allowedExtensions, true);
        }));
    }

    /**
     * @param  IncomingEmailAttachment[]  $attachments
     */
    protected function findMatchingRule(
        IncomingEmailMessage $email,
        array $attachments,
        $rules
    ): ?SpreadsheetEmailRule {
        foreach ($rules as $rule) {
            if ($this->ruleMatches($email, $attachments, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Evaluate rule with AND logic.
     *
     * @param  IncomingEmailAttachment[]  $attachments
     */
    protected function ruleMatches(
        IncomingEmailMessage $email,
        array $attachments,
        SpreadsheetEmailRule $rule
    ): bool {
        // 1. Sender condition
        if (! empty($rule->sender_value)) {
            $senderMatches = $this->matchCondition(
                $email->senderEmail,
                $rule->sender_operator ?? 'contains',
                $rule->sender_value
            ) || ($email->senderName && $this->matchCondition(
                $email->senderName,
                $rule->sender_operator ?? 'contains',
                $rule->sender_value
            ));

            if (! $senderMatches) {
                return false;
            }
        }

        // 2. Subject condition
        if (! empty($rule->subject_value)) {
            if (! $this->matchCondition($email->subject, $rule->subject_operator ?? 'contains', $rule->subject_value)) {
                return false;
            }
        }

        // 3. Body condition
        if (! empty($rule->body_value)) {
            if (! $this->matchCondition($email->bodyText ?? '', $rule->body_operator ?? 'contains', $rule->body_value)) {
                return false;
            }
        }

        // 4. Attachment name condition (at least one attachment in email must match if condition is set)
        if (! empty($rule->attachment_name_value)) {
            $anyAttMatch = false;
            foreach ($attachments as $att) {
                if ($this->matchCondition($att->filename, $rule->attachment_name_operator ?? 'contains', $rule->attachment_name_value)) {
                    $anyAttMatch = true;
                    break;
                }
            }

            if (! $anyAttMatch) {
                return false;
            }
        }

        return true;
    }

    protected function matchCondition(string $haystack, string $operator, string $needle): bool
    {
        $haystack = mb_strtolower(trim($haystack), 'UTF-8');
        $needle = mb_strtolower(trim($needle), 'UTF-8');

        if ($operator === 'equals') {
            return $haystack === $needle;
        }

        // Default: contains
        return str_contains($haystack, $needle);
    }
}
