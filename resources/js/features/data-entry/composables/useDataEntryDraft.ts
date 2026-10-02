import { ref } from 'vue';

export interface ParamDiff {
    parameter_id: number;
    name?: string;
    code?: string | null;
    unit?: string | null;
    decimals?: number;
    alert_1_min?: number | null;
    alert_1_max?: number | null;
    old_value: number | null;
    new_value: number | null;
    status: 'added' | 'updated' | 'cleared';
}

export interface SystemDiff {
    system_id: number;
    isModified: boolean;
    changedParams: ParamDiff[];
    commentChanged: boolean;
    newComment: string | null;
}

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

export function parseNumber(val: string | undefined): number | null {
    if (!val || val.trim() === '') {
        return null;
    }

    const clean = val.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = parseFloat(clean);

    return isNaN(parsed) ? null : parsed;
}

export function computeSystemDiff(
    systemId: number,
    paramInputs: Record<number, string>,
    comment: string,
    parameters: Array<{
        id: number;
        name: string;
        code?: string | null;
        unit?: string | null;
        decimals: number;
        alert_1_min: number | null;
        alert_1_max: number | null;
    }>,
    loadedValues: Record<number, number>,
    loadedComments: Record<number, string>,
): SystemDiff {
    const changedParams: ParamDiff[] = [];

    for (const param of parameters) {
        const raw = paramInputs[param.id];
        const newNum = parseNumber(raw);
        const oldNum =
            loadedValues[param.id] !== undefined
                ? loadedValues[param.id]
                : null;

        if (oldNum === null && newNum !== null) {
            changedParams.push({
                parameter_id: param.id,
                name: param.name,
                code: param.code,
                unit: param.unit,
                decimals: param.decimals,
                alert_1_min: param.alert_1_min,
                alert_1_max: param.alert_1_max,
                old_value: null,
                new_value: newNum,
                status: 'added',
            });
        } else if (oldNum !== null && newNum === null) {
            changedParams.push({
                parameter_id: param.id,
                name: param.name,
                code: param.code,
                unit: param.unit,
                decimals: param.decimals,
                alert_1_min: param.alert_1_min,
                alert_1_max: param.alert_1_max,
                old_value: oldNum,
                new_value: null,
                status: 'cleared',
            });
        } else if (oldNum !== null && newNum !== null) {
            if (Math.abs(oldNum - newNum) > 0.000001) {
                changedParams.push({
                    parameter_id: param.id,
                    name: param.name,
                    code: param.code,
                    unit: param.unit,
                    decimals: param.decimals,
                    alert_1_min: param.alert_1_min,
                    alert_1_max: param.alert_1_max,
                    old_value: oldNum,
                    new_value: newNum,
                    status: 'updated',
                });
            }
        }
    }

    const currentComm = (comment || '').trim();
    const oldComm = (loadedComments[systemId] || '').trim();
    const commentChanged = currentComm !== oldComm;

    const isModified = changedParams.length > 0 || commentChanged;

    return {
        system_id: systemId,
        isModified,
        changedParams,
        commentChanged,
        newComment: commentChanged
            ? currentComm !== ''
                ? currentComm
                : null
            : null,
    };
}

export function useDataEntryDraft(teamSlug: string) {
    const drafts = ref<DraftSystemsMap>({});
    const savedCollectionDate = ref<string | null>(null);
    const savedCollectionTime = ref<string | null>(null);

    function getStorageKey(): string {
        return `siac_data_entry_draft_${teamSlug}`;
    }

    function getResponsibleKey(): string {
        return `siac_data_entry_responsible_${teamSlug}`;
    }

    function getStoredResponsible(): string {
        if (!teamSlug || typeof window === 'undefined') {
            return '';
        }

        try {
            return localStorage.getItem(getResponsibleKey()) || '';
        } catch {
            return '';
        }
    }

    function saveStoredResponsible(name: string): void {
        if (!teamSlug || typeof window === 'undefined') {
            return;
        }

        try {
            if (name && name.trim() !== '') {
                localStorage.setItem(getResponsibleKey(), name.trim());
            } else {
                localStorage.removeItem(getResponsibleKey());
            }
        } catch (e) {
            console.error('Erro ao salvar responsável no localStorage:', e);
        }
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

    return {
        drafts,
        savedCollectionDate,
        savedCollectionTime,
        loadDrafts,
        saveSystemDraft,
        removeSystemDraft,
        clearAllDrafts,
        getStoredResponsible,
        saveStoredResponsible,
    };
}
