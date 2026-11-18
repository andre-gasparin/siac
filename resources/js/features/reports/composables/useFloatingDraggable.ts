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
    options: { persistFloating?: boolean } = {},
) {
    const { persistFloating = false } = options;
    const isFloating = ref(false);
    const isMinimized = ref(false);
    const position = ref<FloatingPosition>({ ...defaultPosition });

    function clampPosition() {
        if (typeof window === 'undefined') {
            return;
        }

        const margin = 8;
        const maxWidth = Math.max(320, window.innerWidth - margin * 2);
        const maxHeight = Math.max(240, window.innerHeight - margin * 2);
        const width = Math.min(position.value.width, maxWidth);
        const height = Math.min(position.value.height, maxHeight);
        const maxX = Math.max(margin, window.innerWidth - width - margin);
        const maxY = Math.max(margin, window.innerHeight - height - margin);

        position.value = {
            x: Math.min(Math.max(margin, position.value.x), maxX),
            y: Math.min(Math.max(margin, position.value.y), maxY),
            width,
            height,
        };
    }

    function toggleFloating() {
        isFloating.value = !isFloating.value;

        if (isFloating.value) {
            isMinimized.value = false;
            clampPosition();
        }

        if (persistFloating && typeof window !== 'undefined') {
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
            const maxX =
                typeof window !== 'undefined'
                    ? Math.max(8, window.innerWidth - 180)
                    : Number.POSITIVE_INFINITY;
            const maxY =
                typeof window !== 'undefined'
                    ? Math.max(8, window.innerHeight - 64)
                    : Number.POSITIVE_INFINITY;

            position.value.x = Math.min(
                maxX,
                Math.max(8, origin.x + moveEvent.clientX - startX),
            );
            position.value.y = Math.min(
                maxY,
                Math.max(8, origin.y + moveEvent.clientY - startY),
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
            if (persistFloating) {
                isFloating.value =
                    localStorage.getItem(`${storageKeyPrefix}-floating`) ===
                    'true';
            }

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

            clampPosition();
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
