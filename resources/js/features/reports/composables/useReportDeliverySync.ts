import { router, usePoll } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import type { ReportRecipientItem } from '@/features/reports/components/ReportRecipientsModal.vue';

interface ReportDeliveryUpdatedPayload {
    reportId: number;
    recipientId: number | null;
    status: 'queued' | 'sent' | 'failed' | 'accessed';
    recipientName?: string | null;
    recipientEmail?: string | null;
    deliveryType?: string | null;
    error?: string | null;
}

interface EchoConnector {
    pusher?: {
        connection?: {
            state?: string;
            bind?: (
                event: string,
                callback: (states: { current: string }) => void,
            ) => void;
            unbind?: (
                event: string,
                callback: (states: { current: string }) => void,
            ) => void;
        };
    };
}

export function useReportDeliverySync(options: {
    reportId: () => number;
    recipients: () => ReportRecipientItem[];
    reloadProps: string[];
}): void {
    const isEchoConnected = ref(false);
    const activeChannelName = ref<string | null>(null);
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;
    let boundConnectionStateHandler:
        ((states: { current: string }) => void) | null = null;

    const queuedSentBatch = new Map<number, string>();
    const queuedFailedBatch = new Map<number, string>();

    const hasQueuedRecipients = computed(() =>
        options.recipients().some((recipient) => Boolean(recipient.is_queued)),
    );

    const previouslyQueued = new Map<
        number,
        { label: string; sendCount: number; lastFailedAt: string | null }
    >();

    watch(
        options.recipients,
        (nextRecipients) => {
            const nextById = new Map(
                nextRecipients.map((recipient) => [recipient.id, recipient]),
            );

            if (!isEchoConnected.value) {
                for (const [id, previous] of previouslyQueued.entries()) {
                    const current = nextById.get(id);

                    if (!current || current.is_queued) {
                        continue;
                    }

                    if (current.send_count > previous.sendCount) {
                        toast.success(`E-mail enviado para ${previous.label}.`);
                    } else if (
                        current.last_failed_at &&
                        current.last_failed_at !== previous.lastFailedAt
                    ) {
                        toast.error(
                            `Falha ao enviar e-mail para ${previous.label}.`,
                        );
                    }
                }
            }

            previouslyQueued.clear();

            for (const recipient of nextRecipients) {
                if (recipient.is_queued) {
                    previouslyQueued.set(recipient.id, {
                        label: recipient.name || recipient.email,
                        sendCount: recipient.send_count,
                        lastFailedAt: recipient.last_failed_at ?? null,
                    });
                }
            }
        },
        { immediate: true, deep: true },
    );

    const { start: startPolling, stop: stopPolling } = usePoll(
        3000,
        { only: options.reloadProps },
        { autoStart: false },
    );

    watch(
        [hasQueuedRecipients, isEchoConnected],
        ([queued, connected]) => {
            if (queued && !connected) {
                startPolling();
            } else {
                stopPolling();
            }
        },
        { immediate: true },
    );

    function flushToasts(): void {
        if (queuedSentBatch.size === 1) {
            const [label] = Array.from(queuedSentBatch.values());
            toast.success(`E-mail enviado para ${label}.`);
        } else if (queuedSentBatch.size > 1) {
            toast.success(
                `${queuedSentBatch.size} e-mails enviados com sucesso.`,
            );
        }

        if (queuedFailedBatch.size === 1) {
            const [label] = Array.from(queuedFailedBatch.values());
            toast.error(`Falha ao enviar e-mail para ${label}.`);
        } else if (queuedFailedBatch.size > 1) {
            toast.error(`Falha ao enviar ${queuedFailedBatch.size} e-mail(s).`);
        }

        queuedSentBatch.clear();
        queuedFailedBatch.clear();
    }

    function scheduleReload(): void {
        if (reloadTimer !== null) {
            clearTimeout(reloadTimer);
        }

        reloadTimer = setTimeout(() => {
            reloadTimer = null;
            router.reload({
                only: options.reloadProps,
                onSuccess: () => {
                    flushToasts();
                },
                onError: () => {
                    queuedSentBatch.clear();
                    queuedFailedBatch.clear();
                },
            });
        }, 200);
    }

    function leaveChannel(): void {
        if (!activeChannelName.value) {
            return;
        }

        try {
            echo()?.leave(activeChannelName.value);
        } catch {
            // Ignore cleanup errors when Echo is unavailable
        }

        activeChannelName.value = null;
    }

    function bindConnectionWatcher(): void {
        if (boundConnectionStateHandler) {
            return;
        }

        try {
            const echoInstance = echo() as unknown as {
                connector?: EchoConnector;
            } | null;
            const connection = echoInstance?.connector?.pusher?.connection;

            if (!connection) {
                return;
            }

            isEchoConnected.value = connection.state === 'connected';
            boundConnectionStateHandler = (states: { current: string }) => {
                isEchoConnected.value = states.current === 'connected';
            };
            connection.bind?.('state_change', boundConnectionStateHandler);
        } catch {
            isEchoConnected.value = false;
        }
    }

    function unbindConnectionWatcher(): void {
        if (!boundConnectionStateHandler) {
            return;
        }

        try {
            const echoInstance = echo() as unknown as {
                connector?: EchoConnector;
            } | null;
            echoInstance?.connector?.pusher?.connection?.unbind?.(
                'state_change',
                boundConnectionStateHandler,
            );
        } catch {
            // Ignore cleanup errors
        }

        boundConnectionStateHandler = null;
    }

    function subscribeToReport(id: number): void {
        leaveChannel();

        if (!id) {
            return;
        }

        try {
            const echoInstance = echo();

            if (!echoInstance) {
                return;
            }

            bindConnectionWatcher();

            const channelName = `reports.${id}`;
            activeChannelName.value = channelName;

            echoInstance
                .private(channelName)
                .listen(
                    '.ReportDeliveryUpdated',
                    (event: ReportDeliveryUpdatedPayload) => {
                        if (event.reportId !== options.reportId()) {
                            return;
                        }

                        const label =
                            event.recipientName ||
                            event.recipientEmail ||
                            'destinatário';

                        if (event.status === 'sent' && event.recipientId) {
                            queuedSentBatch.set(event.recipientId, label);
                        } else if (
                            event.status === 'failed' &&
                            event.recipientId
                        ) {
                            queuedFailedBatch.set(event.recipientId, label);
                        }

                        scheduleReload();
                    },
                );
        } catch {
            isEchoConnected.value = false;
        }
    }

    watch(options.reportId, (id) => subscribeToReport(id), { immediate: true });

    onUnmounted(() => {
        if (reloadTimer !== null) {
            clearTimeout(reloadTimer);
        }

        stopPolling();
        leaveChannel();
        unbindConnectionWatcher();
    });
}
