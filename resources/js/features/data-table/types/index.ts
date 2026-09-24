export interface SystemItem {
    id: number;
    name: string;
    sort_order: number;
}

export interface ParameterItem {
    id: number;
    name: string;
    code?: string;
    unit?: string;
    decimals: number;
    sort_order: number;
    alert_1_min: number | null;
    alert_1_max: number | null;
    monitored_system_id: number;
    system_name: string;
}

export interface DataRow {
    timestamp: string;
    date: string;
    time: string;
    values: Record<number, number>;
}

export interface AverageInfo {
    numeric: number | null;
    formatted: string;
}

export interface ActiveEditingCell {
    rowTimestamp: string;
    date: string;
    time: string;
    parameterId: number;
    parameterName: string;
    unit?: string;
    decimals: number;
    originalValue: number | null;
    currentValue: string;
    x: number;
    y: number;
    yUpper: number;
}

export interface ActiveEditingRowDate {
    oldTimestamp: string;
    originalDate: string;
    originalTime: string;
    newDate: string;
    newTime: string;
    x: number;
    y: number;
    yUpper: number;
}

export interface SystemColorStyle {
    headerBg: string;
    headerText: string;
    colBg: string;
    borderCol: string;
}
