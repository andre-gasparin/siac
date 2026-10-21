export interface EwmaResult {
    available: boolean;
    is_alert: boolean;
    lambda: number;
    level: number;
    lookback: number;
    history_count: number;
    reason?: string;
    value?: number;
    ewma?: number;
    previous_ewma?: number;
    mean?: number;
    sigma?: number;
    sigma_z?: number;
    step?: number;
    lower_limit?: number;
    upper_limit?: number;
    side?: 'upper' | 'lower' | null;
    calculation?: string;
}
