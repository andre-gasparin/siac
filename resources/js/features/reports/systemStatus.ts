export type ReportSystemStatus = 'enabled' | 'editing' | 'disabled';

export interface ReportSystemStatusItem {
    show_data_results: boolean;
    comment?: string | null;
}

export function hasSavedReportComment(
    item: ReportSystemStatusItem | null | undefined,
): boolean {
    return Boolean(
        item?.show_data_results &&
        item?.comment &&
        (item.comment.replace(/<[^>]+>/g, '').trim().length > 0 ||
            item.comment.includes('data-report-chart')),
    );
}

export function resolveReportSystemStatus(
    item: ReportSystemStatusItem | null | undefined,
    isEditing: boolean,
): ReportSystemStatus {
    if (!item?.show_data_results) {
        return 'disabled';
    }

    if (isEditing) {
        return 'editing';
    }

    if (hasSavedReportComment(item)) {
        return 'enabled';
    }

    return 'disabled';
}
