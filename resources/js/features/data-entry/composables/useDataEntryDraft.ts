import { ref } from 'vue';

export interface SystemDraft {
    system_id: number;
    values: Record<number, string>;
    comment: string;
    updated_at?: string;
}

export type DraftSystemsMap = Record<number, SystemDraft>;
export type DraftStorageMap = DraftSystemsMap;

export interface TeamDraftPayload {
    collectionDate: string;
    collectionTime: string;
    systems: DraftSystemsMap;
    updated_at?: string;
}

export function useDataEntryDraft(teamSlug: string) {
    const drafts = ref<DraftSystemsMap>({});
    const savedCollectionDate = ref<string | null>(null);
    const savedCollectionTime = ref<string | null>(null);

    function getStorageKey(): string {
        return `siac_data_entry_draft_${teamSlug}`;
    }

    function loadDrafts(): {
        systems: DraftSystemsMap;
        collectionDate?: string;
        collectionTime?: string;
    } {
        if (!teamSlug || typeof window === 'undefined') {
            drafts.value = {};

            return { systems: {} };
        }

        try {
            const raw = localStorage.getItem(getStorageKey());

            if (raw) {
                const parsed: TeamDraftPayload = JSON.parse(raw);

                if (parsed && typeof parsed === 'object') {
                    if (parsed.systems && typeof parsed.systems === 'object') {
                        drafts.value = parsed.systems || {};
                        savedCollectionDate.value =
                            parsed.collectionDate || null;
                        savedCollectionTime.value =
                            parsed.collectionTime || null;

                        return {
                            systems: drafts.value,
                            collectionDate: parsed.collectionDate,
                            collectionTime: parsed.collectionTime,
                        };
                    } else {
                        drafts.value =
                            (parsed as unknown as DraftSystemsMap) || {};

                        return { systems: drafts.value };
                    }
                }
            }
        } catch (e) {
            console.error('Erro ao ler rascunho do localStorage:', e);
        }

        drafts.value = {};

        return { systems: {} };
    }

    function saveSystemDraft(
        systemId: number,
        values: Record<number, string>,
        comment: string,
        collectionDate?: string,
        collectionTime?: string,
    ): void {
        if (!teamSlug || typeof window === 'undefined') {
            return;
        }

        const currentMap = { ...drafts.value };
        currentMap[systemId] = {
            system_id: systemId,
            values: { ...values },
            comment: comment ?? '',
            updated_at: new Date().toISOString(),
        };

        drafts.value = currentMap;

        const payload: TeamDraftPayload = {
            collectionDate: collectionDate || savedCollectionDate.value || '',
            collectionTime: collectionTime || savedCollectionTime.value || '',
            systems: currentMap,
            updated_at: new Date().toISOString(),
        };

        try {
            localStorage.setItem(getStorageKey(), JSON.stringify(payload));
        } catch (e) {
            console.error('Erro ao salvar rascunho no localStorage:', e);
        }
    }

    function removeSystemDraft(systemId: number): void {
        if (!teamSlug || typeof window === 'undefined') {
            return;
        }

        const currentMap = { ...drafts.value };
        delete currentMap[systemId];
        drafts.value = currentMap;

        const hasAny = Object.values(currentMap).some((d) => {
            const hasVal = Object.values(d.values || {}).some(
                (v) => v !== '' && v !== null && v !== undefined,
            );
            const hasComm = d.comment && d.comment.trim() !== '';

            return hasVal || hasComm;
        });

        try {
            if (!hasAny) {
                localStorage.removeItem(getStorageKey());
            } else {
                const payload: TeamDraftPayload = {
                    collectionDate: savedCollectionDate.value || '',
                    collectionTime: savedCollectionTime.value || '',
                    systems: currentMap,
                    updated_at: new Date().toISOString(),
                };
                localStorage.setItem(getStorageKey(), JSON.stringify(payload));
            }
        } catch (e) {
            console.error('Erro ao atualizar rascunho no localStorage:', e);
        }
    }

    function clearAllDrafts(): void {
        if (!teamSlug || typeof window === 'undefined') {
            return;
        }

        drafts.value = {};
        savedCollectionDate.value = null;
        savedCollectionTime.value = null;

        try {
            localStorage.removeItem(getStorageKey());
        } catch (e) {
            console.error('Erro ao limpar rascunho do localStorage:', e);
        }
    }

    function getDraftSystems(): number[] {
        const ids: number[] = [];

        for (const [key, draft] of Object.entries(drafts.value)) {
            const hasValue = Object.values(draft.values || {}).some(
                (v) => v !== '' && v !== null && v !== undefined,
            );
            const hasComment =
                draft.comment !== undefined &&
                draft.comment !== null &&
                draft.comment.trim() !== '';

            if (hasValue || hasComment) {
                ids.push(Number(key));
            }
        }

        return ids;
    }

    return {
        drafts,
        savedCollectionDate,
        savedCollectionTime,
        loadDrafts,
        saveSystemDraft,
        removeSystemDraft,
        clearAllDrafts,
        getDraftSystems,
    };
}
