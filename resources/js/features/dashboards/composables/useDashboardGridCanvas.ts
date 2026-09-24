import { computed, onBeforeUnmount, ref } from 'vue';
import type { CanvasRect, ComponentItem } from '@/features/dashboards/types';

export const CANVAS_GAP = 16;
export const COLLISION_THRESHOLD = 0.5;
export const COMPACT_BREAKPOINT = 640;
export const DESKTOP_BREAKPOINT = 960;
export const DESKTOP_COLUMN_COUNT = 12;
export const TABLET_COLUMN_COUNT = 6;
export const GRID_Y_STEP = 20;

export function normalizeComponent(
    comp: ComponentItem,
    index: number,
): ComponentItem {
    const gc = comp.grid_config || {};
    const x =
        typeof gc.x === 'number' && gc.x > 12 ? gc.x : (index % 3) * 380 + 20;
    const y =
        typeof gc.y === 'number' && gc.y > 12
            ? gc.y
            : Math.floor(index / 3) * 300 + 20;
    const w =
        typeof gc.w === 'number' && gc.w > 12
            ? gc.w
            : comp.type === 'chart'
              ? 560
              : 340;
    const h =
        typeof gc.h === 'number' && gc.h > 12
            ? gc.h
            : comp.type === 'chart'
              ? 340
              : 200;

    return {
        ...comp,
        grid_config: { x, y, w, h },
    };
}

export function useDashboardGridCanvas(onGridChanged?: () => void) {
    const localComponents = ref<ComponentItem[]>([]);
    const activeComponentId = ref<number | null>(null);
    const isInteracting = ref(false);
    const canvasRef = ref<HTMLElement | null>(null);
    const canvasWidth = ref(0);
    let canvasResizeObserver: ResizeObserver | null = null;

    const isCompactCanvas = computed(
        () => canvasWidth.value > 0 && canvasWidth.value < COMPACT_BREAKPOINT,
    );

    const gridColumnCount = computed(() => {
        if (canvasWidth.value < COMPACT_BREAKPOINT) {
            return 1;
        }

        if (canvasWidth.value < DESKTOP_BREAKPOINT) {
            return TABLET_COLUMN_COUNT;
        }

        return DESKTOP_COLUMN_COUNT;
    });

    const gridColumnWidth = computed(() => {
        const availableWidth = canvasWidth.value || 280;

        return Math.max(
            0,
            (availableWidth - CANVAS_GAP * (gridColumnCount.value + 1)) /
                gridColumnCount.value,
        );
    });

    const orderedComponents = computed(() =>
        [...localComponents.value].sort(
            (first, second) =>
                first.grid_config.y - second.grid_config.y ||
                first.grid_config.x - second.grid_config.x ||
                first.id - second.id,
        ),
    );

    function componentColumnSpan(component: ComponentItem): number {
        if (gridColumnCount.value === 1) {
            return 1;
        }

        return Math.min(
            gridColumnCount.value,
            Math.max(
                1,
                Math.round(
                    (component.grid_config.w + CANVAS_GAP) /
                        (gridColumnWidth.value + CANVAS_GAP),
                ),
            ),
        );
    }

    function gridColumnX(column: number): number {
        return CANVAS_GAP + column * (gridColumnWidth.value + CANVAS_GAP);
    }

    function snappedGridX(rawX: number, columnSpan: number): number {
        const maximumColumn = Math.max(0, gridColumnCount.value - columnSpan);
        const requestedColumn = Math.round(
            (rawX - CANVAS_GAP) / (gridColumnWidth.value + CANVAS_GAP),
        );

        return gridColumnX(
            Math.min(maximumColumn, Math.max(0, requestedColumn)),
        );
    }

    function rectsOverlapWithGap(
        first: CanvasRect,
        second: CanvasRect,
    ): boolean {
        return (
            first.x < second.x + second.w + CANVAS_GAP &&
            first.x + first.w + CANVAS_GAP > second.x &&
            first.y < second.y + second.h + CANVAS_GAP &&
            first.y + first.h + CANVAS_GAP > second.y
        );
    }

    const componentRects = computed(() => {
        const rects = new Map<number, CanvasRect>();
        const placedRects: CanvasRect[] = [];

        orderedComponents.value.forEach((component) => {
            const columnSpan = componentColumnSpan(component);
            const width =
                gridColumnWidth.value * columnSpan +
                CANVAS_GAP * (columnSpan - 1);
            const column = Math.min(
                gridColumnCount.value - columnSpan,
                Math.max(
                    0,
                    Math.round(
                        (component.grid_config.x - CANVAS_GAP) /
                            (gridColumnWidth.value + CANVAS_GAP),
                    ),
                ),
            );
            const height = isCompactCanvas.value
                ? Math.min(Math.max(component.grid_config.h, 180), 360)
                : Math.max(
                      140,
                      Math.round(component.grid_config.h / GRID_Y_STEP) *
                          GRID_Y_STEP,
                  );
            const rect: CanvasRect = {
                x: gridColumnX(column),
                y: CANVAS_GAP,
                w: width,
                h: height,
            };

            while (
                placedRects.some((placedRect) =>
                    rectsOverlapWithGap(rect, placedRect),
                )
            ) {
                rect.y += GRID_Y_STEP;
            }

            rects.set(component.id, rect);
            placedRects.push(rect);
        });

        return rects;
    });

    function componentRect(component: ComponentItem): CanvasRect {
        return (
            componentRects.value.get(component.id) ?? {
                x: CANVAS_GAP,
                y: CANVAS_GAP,
                w: 220,
                h: 180,
            }
        );
    }

    const canvasHeight = computed(() => {
        let maxY = 700;
        componentRects.value.forEach((rect) => {
            const bottom = rect.y + rect.h;

            if (bottom > maxY) {
                maxY = bottom;
            }
        });

        return `${maxY + CANVAS_GAP}px`;
    });

    // ----------------------------------------------------
    // FIXED-GRID DRAGGING
    // ----------------------------------------------------
    const draggingComponent = ref<ComponentItem | null>(null);
    const dragPreview = ref<CanvasRect | null>(null);
    const dragOriginRect = ref<CanvasRect | null>(null);
    const dropTargetId = ref<number | null>(null);
    const dragStartX = ref(0);
    const dragStartY = ref(0);
    const activePointerId = ref<number | null>(null);

    function overlapRatio(first: CanvasRect, second: CanvasRect): number {
        const overlapWidth = Math.max(
            0,
            Math.min(first.x + first.w, second.x + second.w) -
                Math.max(first.x, second.x),
        );
        const overlapHeight = Math.max(
            0,
            Math.min(first.y + first.h, second.y + second.h) -
                Math.max(first.y, second.y),
        );

        if (overlapWidth === 0 || overlapHeight === 0) {
            return 0;
        }

        return (
            (overlapWidth * overlapHeight) /
            Math.min(first.w * first.h, second.w * second.h)
        );
    }

    function findCollision(
        rect: CanvasRect,
        activeId: number,
        minimumRatio = COLLISION_THRESHOLD,
    ): number | null {
        let bestMatchId: number | null = null;
        let bestMatchRatio = minimumRatio;

        for (const [componentId, candidateRect] of componentRects.value) {
            if (componentId === activeId) {
                continue;
            }

            const ratio = overlapRatio(rect, candidateRect);

            if (ratio >= bestMatchRatio) {
                bestMatchId = componentId;
                bestMatchRatio = ratio;
            }
        }

        return bestMatchId;
    }

    function startDragMove(comp: ComponentItem, e: PointerEvent) {
        if (!e.isPrimary || (e.pointerType === 'mouse' && e.button !== 0)) {
            return;
        }

        e.preventDefault();
        activeComponentId.value = comp.id;
        draggingComponent.value = comp;
        isInteracting.value = true;
        activePointerId.value = e.pointerId;

        dragStartX.value = e.clientX;
        dragStartY.value = e.clientY;
        dragOriginRect.value = { ...componentRect(comp) };
        dragPreview.value = { ...componentRect(comp) };
        dropTargetId.value = null;

        window.addEventListener('pointermove', onDragMoving, { passive: true });
        window.addEventListener('pointerup', stopDragMove);
        window.addEventListener('pointercancel', cancelDragMove);
    }

    function onDragMoving(e: PointerEvent) {
        if (
            !draggingComponent.value ||
            !dragOriginRect.value ||
            e.pointerId !== activePointerId.value
        ) {
            return;
        }

        const deltaX = e.clientX - dragStartX.value;
        const deltaY = e.clientY - dragStartY.value;
        const columnSpan = componentColumnSpan(draggingComponent.value);
        const rawX = dragOriginRect.value.x + deltaX;

        dragPreview.value = {
            ...dragOriginRect.value,
            x: snappedGridX(rawX, columnSpan),
            y: Math.max(
                CANVAS_GAP,
                CANVAS_GAP +
                    Math.round(
                        (dragOriginRect.value.y + deltaY - CANVAS_GAP) /
                            GRID_Y_STEP,
                    ) *
                        GRID_Y_STEP,
            ),
        };

        dropTargetId.value = findCollision(
            dragPreview.value,
            draggingComponent.value.id,
        );
    }

    function removeDragListeners() {
        window.removeEventListener('pointermove', onDragMoving);
        window.removeEventListener('pointerup', stopDragMove);
        window.removeEventListener('pointercancel', cancelDragMove);
    }

    function stopDragMove(e: PointerEvent) {
        if (e.pointerId !== activePointerId.value) {
            return;
        }

        removeDragListeners();

        if (
            draggingComponent.value &&
            dragPreview.value &&
            dragOriginRect.value
        ) {
            const target = localComponents.value.find(
                (component) => component.id === dropTargetId.value,
            );
            const minorCollision = findCollision(
                dragPreview.value,
                draggingComponent.value.id,
                Number.EPSILON,
            );

            if (target) {
                const activePosition = {
                    x: draggingComponent.value.grid_config.x,
                    y: draggingComponent.value.grid_config.y,
                };

                draggingComponent.value.grid_config.x = target.grid_config.x;
                draggingComponent.value.grid_config.y = target.grid_config.y;
                target.grid_config.x = activePosition.x;
                target.grid_config.y = activePosition.y;
            } else if (!minorCollision) {
                draggingComponent.value.grid_config.x = dragPreview.value.x;
                draggingComponent.value.grid_config.y = dragPreview.value.y;
            }

            applyCompactedGrid();
            onGridChanged?.();
        }

        resetDragState();
    }

    function cancelDragMove(e?: PointerEvent) {
        if (e && e.pointerId !== activePointerId.value) {
            return;
        }

        removeDragListeners();
        resetDragState();
    }

    function resetDragState() {
        draggingComponent.value = null;
        dragPreview.value = null;
        dragOriginRect.value = null;
        dropTargetId.value = null;
        activePointerId.value = null;
        isInteracting.value = false;
    }

    // ----------------------------------------------------
    // GRID-SNAPPED RESIZING
    // ----------------------------------------------------
    const resizingComponent = ref<ComponentItem | null>(null);
    const resizeStartX = ref(0);
    const resizeStartY = ref(0);
    const initialCompW = ref(0);
    const initialCompH = ref(0);
    const resizeHasCollision = ref(false);

    function startCornerResize(comp: ComponentItem, e: PointerEvent) {
        if (!e.isPrimary || (e.pointerType === 'mouse' && e.button !== 0)) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        activeComponentId.value = comp.id;
        resizingComponent.value = comp;
        isInteracting.value = true;

        resizeStartX.value = e.clientX;
        resizeStartY.value = e.clientY;
        initialCompW.value = componentRect(comp).w;
        initialCompH.value = componentRect(comp).h;
        activePointerId.value = e.pointerId;
        resizeHasCollision.value = false;

        window.addEventListener('pointermove', onCornerResizing, {
            passive: true,
        });
        window.addEventListener('pointerup', stopCornerResize);
        window.addEventListener('pointercancel', cancelCornerResize);
    }

    function onCornerResizing(e: PointerEvent) {
        if (!resizingComponent.value || e.pointerId !== activePointerId.value) {
            return;
        }

        const deltaX = e.clientX - resizeStartX.value;
        const deltaY = e.clientY - resizeStartY.value;
        const currentRect = componentRect(resizingComponent.value);
        const maxWidth = Math.max(
            220,
            canvasWidth.value - currentRect.x - CANVAS_GAP,
        );

        resizingComponent.value.grid_config.w = Math.min(
            maxWidth,
            Math.max(220, Math.round((initialCompW.value + deltaX) / 10) * 10),
        );
        resizingComponent.value.grid_config.h = Math.max(
            140,
            Math.round((initialCompH.value + deltaY) / 10) * 10,
        );
        resizeHasCollision.value =
            findCollision(
                componentRect(resizingComponent.value),
                resizingComponent.value.id,
                Number.EPSILON,
            ) !== null;
    }

    function removeResizeListeners() {
        window.removeEventListener('pointermove', onCornerResizing);
        window.removeEventListener('pointerup', stopCornerResize);
        window.removeEventListener('pointercancel', cancelCornerResize);
    }

    function stopCornerResize(e: PointerEvent) {
        if (e.pointerId !== activePointerId.value) {
            return;
        }

        removeResizeListeners();

        if (resizingComponent.value && resizeHasCollision.value) {
            resizingComponent.value.grid_config.w = initialCompW.value;
            resizingComponent.value.grid_config.h = initialCompH.value;
        } else if (resizingComponent.value) {
            applyCompactedGrid();
            onGridChanged?.();
        }

        resetResizeState();
    }

    function cancelCornerResize(e?: PointerEvent) {
        if (e && e.pointerId !== activePointerId.value) {
            return;
        }

        removeResizeListeners();

        if (resizingComponent.value) {
            resizingComponent.value.grid_config.w = initialCompW.value;
            resizingComponent.value.grid_config.h = initialCompH.value;
        }

        resetResizeState();
    }

    function resetResizeState() {
        resizingComponent.value = null;
        resizeHasCollision.value = false;
        activePointerId.value = null;
        isInteracting.value = false;
    }

    function applyCompactedGrid() {
        const compactedRects = new Map(
            [...componentRects.value.entries()].map(([componentId, rect]) => [
                componentId,
                { ...rect },
            ]),
        );

        localComponents.value.forEach((component) => {
            const rect = compactedRects.get(component.id);

            if (!rect) {
                return;
            }

            component.grid_config.y = rect.y;

            if (!isCompactCanvas.value) {
                component.grid_config.x = rect.x;
                component.grid_config.w = rect.w;
                component.grid_config.h = rect.h;
            }
        });
    }

    function observeCanvasWidth() {
        canvasResizeObserver?.disconnect();

        if (!canvasRef.value) {
            return;
        }

        canvasResizeObserver = new ResizeObserver(([entry]) => {
            canvasWidth.value = Math.floor(entry.contentRect.width);
        });
        canvasResizeObserver.observe(canvasRef.value);
    }

    onBeforeUnmount(() => {
        canvasResizeObserver?.disconnect();
        cancelDragMove();
        cancelCornerResize();
    });

    return {
        localComponents,
        activeComponentId,
        isInteracting,
        canvasRef,
        canvasWidth,
        isCompactCanvas,
        gridColumnCount,
        gridColumnWidth,
        orderedComponents,
        componentRects,
        componentRect,
        canvasHeight,
        draggingComponent,
        dragPreview,
        dragOriginRect,
        dropTargetId,
        resizingComponent,
        resizeHasCollision,
        startDragMove,
        startCornerResize,
        applyCompactedGrid,
        observeCanvasWidth,
    };
}
