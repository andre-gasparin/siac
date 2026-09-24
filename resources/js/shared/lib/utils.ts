import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatBrazilianDate(val: string | null | undefined): string {
    if (!val) {
        return '-';
    }

    const match = val.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (match) {
        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    try {
        const d = new Date(val);

        if (isNaN(d.getTime())) {
            return val;
        }

        return d.toLocaleDateString('pt-BR');
    } catch {
        return val;
    }
}

export function formatBrazilianDateTime(
    val: string | null | undefined,
): string {
    if (!val) {
        return '-';
    }

    try {
        const d = new Date(val);

        if (isNaN(d.getTime())) {
            return val;
        }

        return d.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return val;
    }
}
