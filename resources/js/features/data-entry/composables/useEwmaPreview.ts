import { useHttp } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import { ewma } from '@/routes/data-entry';
import type { EwmaResult } from '@/shared/types/ewma';

export function useEwmaPreview(
    context: () => {
        teamSlug: string;
        collectedAt: string;
        values: { parameter_id: number; value: number }[];
    },
) {
    const results = ref<Record<number, EwmaResult>>({});
    const error = ref('');
    const http = useHttp({
        collected_at: '',
        values: [] as { parameter_id: number; value: number }[],
        lookback: 20,
    });
    let timer: ReturnType<typeof setTimeout> | undefined;
    let version = 0;

    watch(
        context,
        (current) => {
            clearTimeout(timer);
            const requestVersion = ++version;
            results.value = {};
            error.value = '';

            if (!current.collectedAt || !current.values.length) {
                return;
            }

            timer = setTimeout(async () => {
                http.collected_at = current.collectedAt;
                http.values = current.values;

                try {
                    const response = (await http.post(
                        ewma.url({ current_team: current.teamSlug }),
                    )) as { results: Record<number, EwmaResult> };

                    if (requestVersion === version) {
                        results.value = response.results;
                    }
                } catch {
                    if (requestVersion === version) {
                        error.value =
                            'Não foi possível verificar o EWMA. Altere o valor para tentar novamente.';
                    }
                }
            }, 400);
        },
        { deep: true, immediate: true },
    );

    onBeforeUnmount(() => {
        clearTimeout(timer);
        version++;
    });

    return { results, error };
}
