import { onMounted, ref } from 'vue';

export interface FloatingPosition {
    x: number;
    y: number;
    width: number;
    height: number;
}

export function useFloatingDraggable(
    storageKeyPrefix = 'reports-editor',
    defaultPosition: FloatingPosition = {
        x: 80,
        y: 80,
        width: 920,
        height: 650,
    },
) {
    const isFloating = ref(false);
    const isMinimized = ref(false);
    const position = ref<FloatingPosition>({ ...defaultPosition });

    function toggleFloating() {
        isFloating.value = !isFloating.value;

        if (typeof window !== 'undefined') {
            localStorage.setItem(
                `${storageKeyPrefix}-floating`,
                String(isFloating.value),
            );
        }
    }

    function startDrag(event: PointerEvent) {
        if (!isFloating.value) {
            return;
        }

        const startX = event.clientX;
        const startY = event.clientY;
        const origin = { ...position.value };

        const move = (moveEvent: PointerEvent) => {
            position.value.x = Math.max(
                0,
                origin.x + moveEvent.clientX - startX,
            );
            position.value.y = Math.max(
                0,
                origin.y + moveEvent.clientY - startY,
            );
        };

        const stop = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);

            if (typeof window !== 'undefined') {
                localStorage.setItem(
                    `${storageKeyPrefix}-position`,
                    JSON.stringify(position.value),
                );
            }
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
    }

    onMounted(() => {
        if (typeof window !== 'undefined') {
            isFloating.value =
                localStorage.getItem(`${storageKeyPrefix}-floating`) === 'true';
            const saved = localStorage.getItem(`${storageKeyPrefix}-position`);

            if (saved) {
                try {
                    position.value = {
                        ...position.value,
                        ...JSON.parse(saved),
                    };
                } catch {
                    // Fallback to default if JSON is invalid
                }
            }
        }
    });

    return {
        isFloating,
        isMinimized,
        position,
        toggleFloating,
        startDrag,
    };
}
