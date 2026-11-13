export type ParameterCategory = 'ON' | 'LAB' | 'OP' | 'CALC' | 'PORT';

export interface ParameterCategoryOption {
    value: ParameterCategory;
    label: string;
}
