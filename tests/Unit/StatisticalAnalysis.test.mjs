import assert from 'node:assert/strict';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import { effectScope, nextTick, reactive } from 'vue';

const projectRoot = fileURLToPath(new URL('../../', import.meta.url));
const require = createRequire(import.meta.url);
const modules = new Map();

function loadModule(filename) {
    if (modules.has(filename)) {
        return modules.get(filename).exports;
    }

    const source = readFileSync(filename, 'utf8');
    const script = filename.endsWith('.vue')
        ? compileScript(parse(source, { filename }).descriptor, {
              id: 'statistical-analysis-test',
          }).content
        : source;
    const compiled = ts.transpileModule(script, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText;
    const module = { exports: {} };
    modules.set(filename, module);

    function resolveImport(specifier) {
        if (!specifier.startsWith('@/') && !specifier.startsWith('.')) {
            return require(specifier);
        }

        const base = specifier.startsWith('@/')
            ? path.join(projectRoot, 'resources/js', specifier.slice(2))
            : path.resolve(path.dirname(filename), specifier);
        const resolved = [base, `${base}.ts`, path.join(base, 'index.ts')].find(
            (candidate) =>
                existsSync(candidate) && statSync(candidate).isFile(),
        );

        assert.ok(resolved, `Cannot resolve ${specifier} from ${filename}`);

        return loadModule(resolved);
    }

    new Function('require', 'exports', 'module', compiled)(
        resolveImport,
        module.exports,
        module,
    );

    return module.exports;
}

const { useStatisticalAnalysis } = loadModule(
    path.join(
        projectRoot,
        'resources/js/features/statistical-analysis/composables/useStatisticalAnalysis.ts',
    ),
);
const AuditTable = loadModule(
    path.join(
        projectRoot,
        'resources/js/features/statistical-analysis/components/StatisticalAuditTable.vue',
    ),
).default;

const systems = [
    { id: 1, name: 'A', parameters: [{ id: 11 }] },
    { id: 2, name: 'B', parameters: [{ id: 22 }] },
];

function setupAnalysis(context) {
    const scope = effectScope();
    context.after(() => scope.stop());

    return scope.run(() =>
        useStatisticalAnalysis(
            () => 'review-team',
            systems,
            '2026-09-01',
            '2026-09-02',
        ),
    );
}

function interceptRequests(context) {
    const requests = [];
    context.mock.method(
        globalThis,
        'fetch',
        (url, options) =>
            new Promise((resolve, reject) => {
                requests.push({ url, signal: options.signal, resolve, reject });
            }),
    );

    return requests;
}

function respond(request, parameterId) {
    request.resolve({
        ok: true,
        json: async () => ({
            parameters: [{ id: parameterId }],
            results: { [parameterId]: { parameter_id: parameterId } },
        }),
    });
}

test('switching systems aborts the request and ignores its response', async (context) => {
    const requests = interceptRequests(context);
    const analysis = setupAnalysis(context);
    const pending = analysis.loadData();
    analysis.selectedSystemId.value = 2;
    assert.equal(requests[0].signal.aborted, true);
    respond(requests[0], 11);
    await pending;

    assert.deepEqual(analysis.selectedParameterIds.value, [22]);
    assert.deepEqual(analysis.results.value, {});
    assert.deepEqual(analysis.returnedParameters.value, []);
    assert.equal(analysis.activeParameterId.value, null);
    assert.equal(analysis.hasLoaded.value, false);
    assert.equal(analysis.errorMessage.value, null);
});

test('an earlier response cannot replace a newer result', async (context) => {
    const requests = interceptRequests(context);
    const analysis = setupAnalysis(context);
    const earlier = analysis.loadData();
    analysis.selectedSystemId.value = 2;
    const latest = analysis.loadData();
    respond(requests[1], 22);
    await latest;
    respond(requests[0], 11);
    await earlier;

    assert.deepEqual(Object.keys(analysis.results.value), ['22']);
    assert.equal(analysis.activeParameterId.value, 22);
});

test('an aborted request cannot report an error or stop the latest loading state', async (context) => {
    const requests = interceptRequests(context);
    const analysis = setupAnalysis(context);
    const earlier = analysis.loadData();
    const latest = analysis.loadData();
    requests[0].reject(new Error('old failure'));
    await earlier;

    assert.equal(analysis.isLoading.value, true);
    assert.equal(analysis.errorMessage.value, null);
    respond(requests[1], 11);
    await latest;
    assert.equal(analysis.isLoading.value, false);
});

test('leaving the component scope cancels the request', async (context) => {
    const requests = interceptRequests(context);
    const scope = effectScope();
    const analysis = scope.run(() =>
        useStatisticalAnalysis(
            () => 'team',
            systems,
            '2026-09-01',
            '2026-09-02',
        ),
    );
    const pending = analysis.loadData();
    scope.stop();
    assert.equal(requests[0].signal.aborted, true);
    respond(requests[0], 11);
    await pending;
    assert.deepEqual(analysis.results.value, {});
});

test('the generated route carries nested overrides and selected parameters', async (context) => {
    const requests = interceptRequests(context);
    const analysis = setupAnalysis(context);
    analysis.manualOverrides.value = { 11: { mean: 0, std_dev: 2.5 } };
    const pending = analysis.loadData();
    const url = new URL(requests[0].url, 'https://example.test');

    assert.equal(url.pathname, '/review-team/analise-estatistica/data');
    assert.deepEqual(url.searchParams.getAll('parameter_ids[]'), ['11']);
    assert.equal(url.searchParams.get('manual_overrides[11][mean]'), '0');
    assert.equal(url.searchParams.get('manual_overrides[11][std_dev]'), '2.5');
    respond(requests[0], 11);
    await pending;
});

function setupAuditTable(context) {
    const scope = effectScope();
    context.after(() => scope.stop());
    const props = reactive({ result: { parameter_id: 1, rows: rows(40) } });
    const table = scope.run(() =>
        AuditTable.setup(props, { expose() {}, emit() {} }),
    );

    return { props, table };
}

function rows(count) {
    return Array.from({ length: count }, (_, index) => ({
        index: index + 1,
        date: index === 0 ? '10/09/2026' : '11/09/2026',
        time: '10:00',
        raw_value: index,
        ewma: { alert_level: 0 },
        cusum: { alert_level: 0 },
    }));
}

test('changing the parameter resets pagination', async (context) => {
    const { props, table } = setupAuditTable(context);
    table.currentPage.value = 3;
    props.result = { parameter_id: 2, rows: rows(5) };
    await nextTick();

    assert.equal(table.currentPage.value, 1);
    assert.equal(table.paginatedRows.value.length, 5);
});

test('searching from a later page displays matching rows', async (context) => {
    const { table } = setupAuditTable(context);
    table.currentPage.value = 3;
    table.searchQuery.value = '10/09/2026';
    await nextTick();

    assert.equal(table.currentPage.value, 1);
    assert.equal(table.paginatedRows.value.length, 1);
});

test('reloading fewer rows clamps pagination to an existing page', async (context) => {
    const { props, table } = setupAuditTable(context);
    table.currentPage.value = 3;
    props.result.rows = rows(20);
    await nextTick();

    assert.equal(table.currentPage.value, 2);
    assert.equal(table.paginatedRows.value.length, 5);
});
