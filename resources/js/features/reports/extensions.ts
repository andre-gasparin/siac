import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import ReportChartNodeView from './components/ReportChartNodeView.vue';

const iconLabels: Record<string, string> = {
    error: '✕',
    warning: '⚠',
    success: '✓',
    info: 'ⓘ',
};

export const ReportIcon = Node.create({
    name: 'reportIcon',
    inline: true,
    group: 'inline',
    atom: true,

    addAttributes() {
        return { kind: { default: 'info' } };
    },

    parseHTML() {
        return [{ tag: 'span[data-report-icon]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'span',
            mergeAttributes(HTMLAttributes, {
                'data-report-icon': node.attrs.kind,
                class: `report-icon report-icon-${node.attrs.kind}`,
            }),
            iconLabels[node.attrs.kind] ?? iconLabels.info,
        ];
    },
});

export const ParameterReference = Node.create({
    name: 'parameterReference',
    inline: true,
    group: 'inline',
    atom: true,

    addAttributes() {
        return {
            ids: { default: [] },
            label: { default: '' },
        };
    },

    parseHTML() {
        return [{ tag: 'strong[data-parameter-ids]' }];
    },

    renderHTML({ node }) {
        return [
            'strong',
            { 'data-parameter-ids': JSON.stringify(node.attrs.ids) },
            node.attrs.label,
        ];
    },
});

export const ReportChart = Node.create({
    name: 'reportChart',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            id: { default: '' },
            title: { default: 'Gráfico' },
            height: { default: 300 },
            startDate: { default: '' },
            endDate: { default: '' },
            teamSlug: { default: '' },
            series: { default: [] },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-report-chart]' }];
    },

    renderHTML({ node }) {
        return [
            'div',
            {
                'data-report-chart': node.attrs.id,
                'data-chart-id': node.attrs.id,
            },
            `Gráfico: ${node.attrs.title}`,
        ];
    },

    addNodeView() {
        return VueNodeViewRenderer(ReportChartNodeView);
    },
});
