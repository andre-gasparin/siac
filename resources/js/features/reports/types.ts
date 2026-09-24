export interface ReportSystem {
    id: number;
    name: string;
    sort_order: number;
}

export interface ReportParameter {
    id: number;
    name: string;
    code?: string | null;
    tag?: string | null;
    unit?: string | null;
    system_name?: string;
    display_name?: string;
}

export interface ReportHistoryItem {
    id: number;
    date_reference: string;
    document: Record<string, unknown> | null;
    html: string;
    preview: string;
}

export interface ReportPhrase {
    id: number;
    report_id: number;
    parent_report_item_id: number | null;
    system_name: string;
    document: Record<string, unknown> | null;
    html: string;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    updated_at: string;
}

export interface ReportContext {
    state: 'new_report' | 'new_item' | 'existing';
    report: { id: number; status: string; date_reference: string } | null;
    item: ReportPhrase | null;
    system: { id: number; name: string };
    systems: ReportSystem[];
    parameters: ReportParameter[];
    latest_comment: ReportHistoryItem | null;
    phrases: ReportPhrase[];
}

export interface ReportChartSeriesConfig {
    parameter_id: number;
    label: string;
    chart_type: 'line' | 'bar' | 'area';
    color: string;
    stroke_width: number;
    axis_position: 'left' | 'right';
    min_val: number | null;
    max_val: number | null;
    show_points: boolean;
    show_values: boolean;
}

export interface ReportChartConfig {
    id: string;
    title: string;
    height: number;
    startDate: string;
    endDate: string;
    teamSlug: string;
    series: ReportChartSeriesConfig[];
}

export interface ChartFavoriteSeries {
    id?: number;
    parameter_id: number;
    system_name: string;
    label: string;
    chart_type: 'line' | 'bar' | 'area';
    color: string;
    stroke_width: number;
    axis_position: 'left' | 'right';
    min_val: number | null;
    max_val: number | null;
    show_points: boolean;
    show_values: boolean;
}

export interface ChartFavoriteTemplate {
    id: number;
    name: string;
    is_favorite: boolean;
    options?: { height?: number } | null;
    series: ChartFavoriteSeries[];
}
