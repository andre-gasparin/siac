import { computed, ref } from 'vue';

export function useDashboardDateFilter(
    defaultPreset: '7d' | '30d' | '90d' | 'custom' = '7d',
) {
    const selectedPreset = ref<'7d' | '30d' | '90d' | 'custom'>(defaultPreset);
    const customStart = ref('');
    const customEnd = ref('');

    const dateRange = computed(() => {
        const end = new Date();
        const start = new Date();

        if (selectedPreset.value === '7d') {
            start.setDate(end.getDate() - 7);
        } else if (selectedPreset.value === '30d') {
            start.setDate(end.getDate() - 30);
        } else if (selectedPreset.value === '90d') {
            start.setDate(end.getDate() - 90);
        } else if (
            selectedPreset.value === 'custom' &&
            customStart.value &&
            customEnd.value
        ) {
            return {
                start: customStart.value,
                end: customEnd.value,
            };
        }

        return {
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0],
        };
    });

    return {
        selectedPreset,
        customStart,
        customEnd,
        dateRange,
    };
}
