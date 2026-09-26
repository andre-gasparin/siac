export interface TaskUser {
    id: number;
    name: string;
    email: string;
}

export interface TaskChecklistItem {
    id: number;
    kanban_task_id: number;
    title: string;
    is_completed: boolean;
    order: number;
}

export interface TaskAttachment {
    id: number;
    kanban_task_id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    created_at: string;
    user?: {
        id: number;
        name: string;
    } | null;
}

export interface Task {
    id: number;
    kanban_board_id: number;
    kanban_column_id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    is_completed: boolean;
    completed_at: string | null;
    assigned_to_user_id: number | null;
    assigned_user: TaskUser | null;
    order: number;
    created_at: string;
    checklists_count: number;
    completed_checklists_count: number;
    attachments_count: number;
    checklists: TaskChecklistItem[];
    attachments: TaskAttachment[];
}

export interface Column {
    id: number;
    kanban_board_id: number;
    name: string;
    color: string | null;
    order: number;
    tasks: Task[];
}

export interface Board {
    id: number;
    name: string;
    description: string | null;
    order: number;
    created_by: number | null;
    columns: Column[];
}

export interface BoardSummary {
    id: number;
    name: string;
    order: number;
}

export interface OnlineUser {
    id: number;
    name: string;
    email: string;
}
