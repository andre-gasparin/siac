import type {
    ParameterCategoryCode,
    ReportTableData,
    ReportTableParameter,
} from '@/features/reports/types';

export const PARAMETER_CATEGORY_META: Record<
    ParameterCategoryCode,
    { code: ParameterCategoryCode; label: string; badgeClass: string }
> = {
    ON: {
        code: 'ON',
        label: 'Online',
        badgeClass:
            'bg-[#dce9ff] text-[#244bb0] dark:bg-[#29456f] dark:text-[#c1d8ff]',
    },
    LAB: {
        code: 'LAB',
        label: 'Laboratório',
        badgeClass:
            'bg-[#f0e3ff] text-[#7526af] dark:bg-[#493560] dark:text-[#e7c9ff]',
    },
    OP: {
        code: 'OP',
        label: 'Operação',
        badgeClass:
            'bg-[#d7f4fa] text-[#12657c] dark:bg-[#234a5d] dark:text-[#a6e4f4]',
    },
    CALC: {
        code: 'CALC',
        label: 'Calculado',
        badgeClass:
            'bg-[#ffedb8] text-[#914500] dark:bg-[#56421f] dark:text-[#ffdc91]',
    },
    PORT: {
        code: 'PORT',
        label: 'Portátil',
        badgeClass:
            'bg-[#ddf3e9] text-[#177453] dark:bg-[#254d40] dark:text-[#a8e1c4]',
    },
};

export const PARAMETER_CATEGORY_CODES: ParameterCategoryCode[] = [
    'ON',
    'LAB',
    'OP',
    'CALC',
    'PORT',
];

export function getCategoryMeta(category?: string | null) {
    if (!category) {
        return null;
    }

    const upper = category.toUpperCase() as ParameterCategoryCode;

    return PARAMETER_CATEGORY_META[upper] ?? null;
}

export function hasLimits(parameter: ReportTableParameter): boolean {
    return parameter.alert_1_min !== null || parameter.alert_1_max !== null;
}

export function isValueOutsideLimits(
    value: number | null | undefined,
    parameter: ReportTableParameter,
): boolean {
    if (value === undefined || value === null || Number.isNaN(value)) {
        return false;
    }

    if (parameter.alert_1_min !== null && value < parameter.alert_1_min) {
        return true;
    }

    if (parameter.alert_1_max !== null && value > parameter.alert_1_max) {
        return true;
    }

    return false;
}

export function formatNumericValue(
    value: number | null | undefined,
    decimals: number,
): string {
    if (value === undefined || value === null || Number.isNaN(value)) {
        return '—';
    }

    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

export function formatLimitsLabel(parameter: ReportTableParameter): string {
    const { alert_1_min: min, alert_1_max: max, decimals } = parameter;

    if (min !== null && max !== null) {
        return `${formatNumericValue(min, decimals)} – ${formatNumericValue(max, decimals)}`;
    }

    if (min !== null) {
        return `≥ ${formatNumericValue(min, decimals)}`;
    }

    if (max !== null) {
        return `≤ ${formatNumericValue(max, decimals)}`;
    }

    return '—';
}

export interface DeviationEntry {
    parameter: ReportTableParameter;
    timestamp: string;
    time: string;
    date?: string;
    value: number;
}

export interface SystemMetricsSummary {
    total: number;
    eligible: number;
    deviations: number;
    conformityPct: string;
    deviationPct: string;
    conformityRatio: number;
    deviationEntries: DeviationEntry[];
}

export function formatPercentBr(part: number, total: number): string {
    if (!total) {
        return '—';
    }

    return `${((part / total) * 100).toFixed(1).replace('.', ',')}%`;
}

export function computeSystemStats(
    data?: ReportTableData | null,
    filterSystemId?: number | null,
): SystemMetricsSummary {
    if (!data || !data.parameters.length || !data.rows.length) {
        return {
            total: 0,
            eligible: 0,
            deviations: 0,
            conformityPct: '—',
            deviationPct: '—',
            conformityRatio: 100,
            deviationEntries: [],
        };
    }

    const parameters =
        filterSystemId != null
            ? data.parameters.filter(
                  (p) => p.monitored_system_id === filterSystemId,
              )
            : data.parameters;

    let total = 0;
    let eligible = 0;
    let deviations = 0;
    const deviationEntries: DeviationEntry[] = [];

    for (const parameter of parameters) {
        const bounded = hasLimits(parameter);

        for (const row of data.rows) {
            const value = row.values[parameter.id];

            if (value === undefined || value === null || Number.isNaN(value)) {
                continue;
            }

            total += 1;

            if (bounded) {
                eligible += 1;

                if (isValueOutsideLimits(value, parameter)) {
                    deviations += 1;
                    deviationEntries.push({
                        parameter,
                        timestamp: row.timestamp,
                        time: row.time,
                        date: row.date,
                        value,
                    });
                }
            }
        }
    }

    const compliant = Math.max(0, eligible - deviations);

    return {
        total,
        eligible,
        deviations,
        conformityPct: formatPercentBr(compliant, eligible),
        deviationPct: formatPercentBr(deviations, eligible),
        conformityRatio: eligible ? (compliant / eligible) * 100 : 100,
        deviationEntries,
    };
}

export function formatDateBr(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    if (year && month && day) {
        return `${day}/${month}/${year}`;
    }

    return value;
}

export function formatDateTimeBr(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

export function formatReportHour(time: string): string {
    if (!time) {
        return '';
    }

    if (/^\d{2}:\d{2}$/.test(time)) {
        return time.replace(':', 'h');
    }

    return time;
}
