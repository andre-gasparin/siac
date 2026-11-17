import { mergeAttributes, Node } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import ReportChartNodeView from './components/ReportChartNodeView.vue';
import ReportImageNodeView from './components/ReportImageNodeView.vue';

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
        return [
            {
                tag: 'div[data-report-chart]',
                getAttrs: (element) => {
                    if (typeof element === 'string') {
                        return false;
                    }

                    const rawConfig = element.getAttribute('data-chart-config');

                    if (rawConfig) {
                        try {
                            return JSON.parse(rawConfig);
                        } catch {
                            // Fallback to basic id parsing when config JSON is invalid
                        }
                    }

                    const id =
                        element.getAttribute('data-report-chart') ||
                        element.getAttribute('data-chart-id') ||
                        '';

                    return { id };
                },
            },
        ];
    },

    renderHTML({ node }) {
        return [
            'div',
            {
                'data-report-chart': node.attrs.id,
                'data-chart-id': node.attrs.id,
                'data-chart-config': JSON.stringify(node.attrs),
            },
            `Gráfico: ${node.attrs.title}`,
        ];
    },

    addNodeView() {
        return VueNodeViewRenderer(ReportChartNodeView);
    },
});

export const ResizableImage = Image.extend({
    name: 'image',

    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: '360px',
                parseHTML: (element) =>
                    element.getAttribute('width') ||
                    element.style.width ||
                    '360px',
                renderHTML: (attributes) => {
                    if (!attributes.width) {
                        return {};
                    }

                    return {
                        width: attributes.width,
                        style: `width: ${attributes.width}; max-width: 100%; height: auto;`,
                    };
                },
            },
        };
    },

    addNodeView() {
        return VueNodeViewRenderer(ReportImageNodeView);
    },
});
