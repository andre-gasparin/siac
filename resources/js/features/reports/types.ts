import type {
    ChartAxesConfig,
    ChartSeries,
} from '@/shared/components/charts/EChartRenderer.vue';

export interface ReportSystem {
    id: number;
    name: string;
    sort_order: number;
}

export interface ReportParameter {
    id: number;
    monitored_system_id?: number;
    name: string;
    code?: string | null;
    tag?: string | null;
    unit?: string | null;
    alert_1_min?: number | null;
    alert_1_max?: number | null;
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
    charts?: Record<string, ChartSeries[]>;
    show_data_results: boolean;
    hide_data: boolean;
    is_stopped: boolean;
    updated_at: string;
}

export interface ReportContext {
    state: 'new_report' | 'new_item' | 'existing';
    report: { id: number; status: string; date_reference: string } | null;
    item: ReportPhrase | null;
    default_hide_data?: boolean;
    system: { id: number; name: string };
    systems: ReportSystem[];
    parameters: ReportParameter[];
    latest_comment: ReportHistoryItem | null;
    phrases: ReportPhrase[];
}

export interface ReportChartAxisConfig {
    min: number | null;
    max: number | null;
}

export interface ReportChartAxesConfig {
    left?: ReportChartAxisConfig;
    right?: ReportChartAxisConfig;
}

export interface ReportChartSeriesConfig {
    parameter_id: number;
    label: string;
    chart_type: 'line' | 'bar' | 'area';
    color: string;
    stroke_width: number;
    axis_position: 'left' | 'right';
    min_val?: number | null;
    max_val?: number | null;
    show_limits?: boolean;
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
    axes?: ChartAxesConfig | null;
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
    parameter_min?: number | null;
    parameter_max?: number | null;
    show_limits?: boolean;
    show_points: boolean;
    show_values: boolean;
}

export interface ChartFavoriteTemplate {
    id: number;
    name: string;
    is_favorite: boolean;
    options?: { height?: number } | null;
    axes?: ReportChartAxesConfig | null;
    series: ChartFavoriteSeries[];
}
