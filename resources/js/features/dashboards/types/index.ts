export interface DashboardItem {
    id: number;
    title: string;
    is_public: boolean;
    user_id: number;
    team_id: number;
}

export interface ComponentItem {
    id: number;
    type: 'indicator' | 'text' | 'chart';
    grid_config: { x: number; y: number; w: number; h: number };
    settings: Record<string, any>;
}

export interface CanvasRect {
    x: number;
    y: number;
    w: number;
    h: number;
}
