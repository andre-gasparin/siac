export interface CellMapping {
    cell: string;
    monitored_system_id: number;
    parameter_id: number;
    date_cell?: string;
    time_cell?: string;
    multiplier: number;
    description?: string;
    row_or_col?: number | string;
}

export interface SheetConfig {
    sheet_identifier_type: 'index' | 'name';
    sheet_identifier_value: number | string;
    sheet_name?: string;
    date_mode:
        | 'cell_reference'
        | 'horizontal_series'
        | 'vertical_series'
        | 'upload_date_match'
        | 'all_dates_scan';
    date_cell?: string;
    time_cell?: string;
    date_column?: string;
    date_row?: number | string | null;
    time_column?: string | null;
    time_row?: number | string | null;
    allowed_times?: string[] | null;
    empty_values_mode?: 'ignore' | 'save_empty';
    ignore_empty_cells?: boolean;
    mappings: CellMapping[];
}

export interface TemplateConfig {
    version: number;
    empty_values_mode?: 'ignore' | 'save_empty';
    ignore_empty_cells?: boolean;
    sheets: SheetConfig[];
}

export interface SpreadsheetTemplateItem {
    id: number;
    team_id: number;
    name: string;
    description?: string | null;
    config: TemplateConfig;
    is_active: boolean;
    created_by?: number | null;
    creator?: { id: number; name: string };
    updater?: { id: number; name: string };
    team?: { id: number; name: string; slug: string };
    batches_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface SpreadsheetImportBatchItem {
    id: number;
    team_id: number;
    user_id?: number | null;
    spreadsheet_template_id?: number | null;
    file_name: string;
    file_path?: string | null;
    reference_date?: string | null;
    status: 'completed' | 'reverted' | 'failed';
    saved_values_count: number;
    created_at: string;
    reverted_at?: string | null;
    team?: { id: number; name: string; slug: string };
    user?: { id: number; name: string };
    template?: { id: number; name: string };
    reverted_by?: { id: number; name: string };
}

export interface PreviewItem {
    sheet_name: string;
    cell: string;
    system_id: number;
    system_name: string;
    parameter_id: number;
    parameter_name: string;
    unit: string | null;
    raw_value: unknown;
    multiplier: number;
    final_value: number | null;
    measured_at: string;
    measured_date: string;
    is_update: boolean;
    existing_value: number | null;
    status: 'valid' | 'empty_or_invalid';
    error_message?: string | null;
}

export interface PreviewResponse {
    total_extracted: number;
    total_new: number;
    total_updates: number;
    total_empty_or_invalid: number;
    items: PreviewItem[];
    warnings: string[];
    sheets_found: string[];
}

export interface SpreadsheetEmailRuleItem {
    id: number;
    team_id: number;
    spreadsheet_template_id: number;
    name: string;
    is_active: boolean;
    priority: number;
    subject_operator?: 'contains' | 'equals' | null;
    subject_value?: string | null;
    body_operator?: 'contains' | null;
    body_value?: string | null;
    sender_operator?: 'contains' | 'equals' | null;
    sender_value?: string | null;
    attachment_name_operator?: 'contains' | null;
    attachment_name_value?: string | null;
    date_extraction_source?:
        'auto' | 'filename' | 'subject' | 'body' | 'spreadsheet' | null;
    date_extraction_pattern?: string | null;
    template?: { id: number; name: string };
    creator?: { id: number; name: string };
    updater?: { id: number; name: string };
    inbox_items_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface PendingConfirmationItem {
    id: number;
    team_id: number;
    spreadsheet_email_rule_id?: number | null;
    spreadsheet_template_id: number;
    email_message_id?: string | null;
    sender_email: string;
    sender_name?: string | null;
    subject: string;
    body_snippet?: string | null;
    email_received_at?: string | null;
    file_name: string;
    file_path: string;
    file_size_bytes: number;
    extracted_reference_date?: string | null;
    status: 'pending_confirmation' | 'queued' | 'discarded';
    team?: { id: number; name: string; slug: string };
    template?: { id: number; name: string };
    rule?: { id: number; name: string };
    created_at?: string;
}

export interface ImportQueueItem {
    id: number;
    team_id: number;
    user_id?: number | null;
    spreadsheet_template_id: number;
    source_type: 'manual_upload' | 'email';
    spreadsheet_email_inbox_item_id?: number | null;
    file_name: string;
    file_path: string;
    reference_date?: string | null;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    attempts: number;
    error_message?: string | null;
    error_trace?: string | null;
    saved_values_count: number;
    spreadsheet_import_batch_id?: number | null;
    started_at?: string | null;
    completed_at?: string | null;
    created_at?: string;
    team?: { id: number; name: string; slug: string };
    template?: { id: number; name: string };
    user?: { id: number; name: string };
    batch?: { id: number; saved_values_count: number };
}
