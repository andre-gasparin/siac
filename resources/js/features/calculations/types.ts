export interface CalculationParameter {
    id: number;
    name: string;
    monitored_system_id: number;
}

export interface CalculationSystem {
    id: number;
    name: string;
    parameters: CalculationParameter[];
}

export interface CalculationRule {
    id: number;
    name: string;
    type: string;
    primary_parameter_id: number;
    secondary_parameter_id: number;
    destination_parameter_id: number;
    primary_multiplier: number | null;
    secondary_multiplier: number | null;
    result_multiplier: number | null;
}

export interface CalculationRun {
    id: number;
    calculation_rule_id: number;
    start_date: string;
    end_date: string;
    status: string;
    processed_count: number;
    total_count: number;
    created_count: number;
    updated_count: number;
    removed_count: number;
    error: string | null;
}
