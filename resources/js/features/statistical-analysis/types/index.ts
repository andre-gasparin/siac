export interface SystemItem {
    id: number;
    name: string;
    sort_order: number;
    parameters?: ParameterItem[];
}

export interface ParameterItem {
    id: number;
    name: string;
    code?: string | null;
    tag?: string | null;
    unit?: string | null;
    decimals: number;
    sort_order: number;
    monitored_system_id: number;
}

export interface LevelConfig {
    level: number;
    multiplier: number;
    ucl?: number;
    lcl?: number;
    threshold?: number;
}

export interface ClosestLimit {
    target_name: string;
    target_value: number;
    margin: number;
    is_exceeded: boolean;
    percentage_used: number;
}

export interface EwmaMemorial {
    step: number;
    lambda: number;
    raw_value: number;
    previous_z: number;
    z_value: number;
    sigma_0: number;
    mu_0: number;
    sigma_z: number;
    formula_str: string;
    substitution_str: string;
    sigma_formula_str: string;
    levels: LevelConfig[];
    alert_level: number;
    violated_side?: 'upper' | 'lower' | null;
    closest_limit: ClosestLimit;
}

export interface CusumMemorial {
    step: number;
    k_factor: number;
    k_value: number;
    raw_value: number;
    previous_c_pos: number;
    previous_c_neg: number;
    c_pos: number;
    c_neg: number;
    sigma_0: number;
    mu_0: number;
    formula_pos_str: string;
    substitution_pos_str: string;
    formula_neg_str: string;
    substitution_neg_str: string;
    levels: LevelConfig[];
    alert_level: number;
    violated_side?: 'pos' | 'neg' | 'both' | null;
    closest_threshold: ClosestLimit;
}

export interface EwmaRowData {
    z_value: number;
    sigma_z: number;
    alert_level: number;
    violated_side?: 'upper' | 'lower' | null;
    levels: LevelConfig[];
    closest_limit: ClosestLimit;
    memorial: EwmaMemorial;
}

export interface CusumRowData {
    c_pos: number;
    c_neg: number;
    k_value: number;
    alert_level: number;
    violated_side?: 'pos' | 'neg' | 'both' | null;
    levels: LevelConfig[];
    closest_threshold: ClosestLimit;
    memorial: CusumMemorial;
}

export interface AnalysisRow {
    index: number;
    overall_index: number;
    timestamp: string;
    date: string;
    time: string;
    raw_value: number;
    ewma: EwmaRowData;
    cusum: CusumRowData;
}

export interface BaselineInfo {
    count: number;
    calculated_mu_0: number;
    calculated_sigma_0: number;
    effective_mu_0: number;
    effective_sigma_0: number;
    is_overridden_mean: boolean;
    is_overridden_std_dev: boolean;
}

export interface MethodSummary {
    in_control_count: number;
    in_control_percentage: number;
    alert_counts: Record<number, number>;
}

export interface ParameterSummary {
    total_analyzed: number;
    ewma: MethodSummary;
    cusum: MethodSummary;
}

export interface EwmaChartData {
    z_values: number[];
    center_line: number;
    levels: number[];
    ucl_series: Record<number, number[]>;
    lcl_series: Record<number, number[]>;
}

export interface CusumChartData {
    c_pos_values: number[];
    c_neg_values: number[];
    levels: number[];
    h_thresholds: number[];
}

export interface ParameterChartData {
    timestamps: string[];
    dates: string[];
    raw_values: number[];
    ewma: EwmaChartData;
    cusum: CusumChartData;
}

export interface ParameterAnalysisResult {
    parameter_id: number;
    parameter_name: string;
    unit?: string | null;
    decimals: number;
    baseline: BaselineInfo;
    summary: ParameterSummary;
    chart_data: ParameterChartData;
    rows: AnalysisRow[];
}

export interface AnalysisConfig {
    system_id: number;
    start_date: string;
    end_date: string;
    lookback_value: number;
    lookback_unit: 'days' | 'samples';
    frequency: 'raw' | 'daily_avg';
    num_alert_levels: number;
    ewma_lambda: number;
    ewma_levels: number[];
    cusum_k: number;
    cusum_levels: number[];
}

export interface AnalysisResponse {
    config: AnalysisConfig;
    parameters: ParameterItem[];
    results: Record<number, ParameterAnalysisResult>;
}

export interface ManualOverride {
    mean?: number | null;
    std_dev?: number | null;
}
