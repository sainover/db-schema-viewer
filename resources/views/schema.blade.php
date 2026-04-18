<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DB Schema Viewer</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 260px;
            --toolbar-h: 48px;
            --clr-primary: #4f46e5;
            --clr-bg: #f1f5f9;
            --clr-surface: #ffffff;
            --clr-border: #e2e8f0;
            --clr-text: #1e293b;
            --clr-muted: #64748b;
            --clr-type: #7c3aed;
            --clr-pk: #f59e0b;
            --clr-fk: #10b981;
        }

        html, body { height: 100%; overflow: hidden; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--clr-bg);
            display: flex;
        }

        /* ── Sidebar ─────────────────────────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            flex-shrink: 0;
            background: var(--clr-surface);
            border-right: 1px solid var(--clr-border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid var(--clr-border);
        }

        .sidebar-header h1 {
            font-size: 14px;
            font-weight: 700;
            color: var(--clr-text);
            letter-spacing: -0.02em;
        }

        .sidebar-header p {
            font-size: 12px;
            color: var(--clr-muted);
            margin-top: 3px;
        }

        .search-wrap {
            padding: 10px 12px;
            border-bottom: 1px solid var(--clr-border);
        }

        .search-wrap input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid var(--clr-border);
            border-radius: 6px;
            font-size: 12px;
            outline: none;
            background: var(--clr-bg);
            color: var(--clr-text);
        }

        .search-wrap input:focus { border-color: var(--clr-primary); }

        .table-list { overflow-y: auto; flex: 1; }

        .table-item {
            padding: 9px 14px;
            cursor: pointer;
            font-size: 12.5px;
            color: var(--clr-text);
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid transparent;
            transition: background 0.1s, border-color 0.1s;
        }

        .table-item:hover { background: #f8faff; }

        .table-item.active {
            background: #eff2ff;
            border-left-color: var(--clr-primary);
            font-weight: 600;
        }

        .table-item .badge {
            margin-left: auto;
            font-size: 11px;
            background: var(--clr-bg);
            border: 1px solid var(--clr-border);
            padding: 1px 6px;
            border-radius: 10px;
            color: var(--clr-muted);
            flex-shrink: 0;
        }

        /* ── Main area ───────────────────────────────────────────────── */
        #main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        #toolbar {
            height: var(--toolbar-h);
            background: var(--clr-surface);
            border-bottom: 1px solid var(--clr-border);
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            flex-shrink: 0;
        }

        .btn {
            padding: 5px 11px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid var(--clr-border);
            border-radius: 6px;
            background: var(--clr-surface);
            cursor: pointer;
            color: var(--clr-text);
            transition: all 0.12s;
            white-space: nowrap;
        }

        .btn:hover { background: var(--clr-bg); border-color: #a5b4fc; color: var(--clr-primary); }

        .toolbar-sep { width: 1px; height: 20px; background: var(--clr-border); margin: 0 2px; }

        .zoom-label { font-size: 12px; color: var(--clr-muted); min-width: 44px; text-align: center; }

        #container { flex: 1; }

        /* ── Table node HTML (rendered inside SVG foreignObject) ──────── */
        .tn {
            width: 100%;
            height: 100%;
            background: var(--clr-surface);
            border: 1.5px solid var(--clr-border);
            border-radius: 8px;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
        }

        .tn-head {
            padding: 7px 12px;
            font-size: 12.5px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .tn-head svg { flex-shrink: 0; opacity: 0.85; }

        .tn-row {
            padding: 0 12px;
            height: 26px;
            font-size: 11.5px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tn-row:last-child { border-bottom: none; }

        .tn-name { flex: 1; color: var(--clr-text); font-weight: 500; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tn-type { color: var(--clr-type); font-size: 10.5px; flex-shrink: 0; }
        .tn-pk  { color: var(--clr-pk);  font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; flex-shrink: 0; }
        .tn-fk  { color: var(--clr-fk);  font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; flex-shrink: 0; }
        .tn-null { color: #cbd5e1; font-size: 9.5px; flex-shrink: 0; }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="sidebar-header">
        <h1>DB Schema Viewer</h1>
        <p id="table-count">—</p>
    </div>
    <div class="search-wrap">
        <input id="search" type="text" placeholder="Search tables…" autocomplete="off">
    </div>
    <div class="table-list" id="table-list"></div>
</div>

<div id="main">
    <div id="toolbar">
        <button class="btn" id="btn-zoom-out">−</button>
        <span class="zoom-label" id="zoom-label">100%</span>
        <button class="btn" id="btn-zoom-in">+</button>
        <div class="toolbar-sep"></div>
        <button class="btn" id="btn-fit">Fit view</button>
    </div>
    <div id="container"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@antv/x6@2/dist/index.js"></script>
<script>
'use strict';

// ═══════════════════════════════════════════════════════════════════
// 1. CONFIGURATION
// ═══════════════════════════════════════════════════════════════════

const CONFIG = {
    node: {
        width: 240,
        headerH: 34,
        rowH: 26,
    },
    layout: {
        cols: 5,
        hGap: 80,
        vGap: 60,
    },
    palette: ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#2563eb'],
};

// Raw data injected by Laravel
const RAW_TABLES = @json($tables);


// ═══════════════════════════════════════════════════════════════════
// 2. DATA PROCESSING
//    Transforms raw Laravel Schema output into a clean model.
// ═══════════════════════════════════════════════════════════════════

const DataProcessor = {
    process(raw) {
        return Object.entries(raw).map(([name, data], idx) => {
            const pkCols  = this.primaryKeyColumns(data.indexes);
            const fkCols  = this.foreignKeyColumns(data.foreign_keys);

            return {
                name,
                color: CONFIG.palette[idx % CONFIG.palette.length],
                columns: data.columns.map(col => ({
                    name:     col.name,
                    type:     col.type_name ?? col.type ?? '',
                    nullable: col.nullable,
                    isPk:     pkCols.has(col.name),
                    isFk:     fkCols.has(col.name),
                })),
                foreignKeys: data.foreign_keys.map(fk => ({
                    sourceCol:   fk.columns[0] ?? null,
                    targetTable: fk.foreign_table,
                    targetCol:   fk.foreign_columns[0] ?? null,
                })),
            };
        });
    },

    primaryKeyColumns(indexes) {
        const cols = new Set();
        for (const idx of indexes) {
            if (idx.primary) idx.columns.forEach(c => cols.add(c));
        }
        return cols;
    },

    foreignKeyColumns(foreignKeys) {
        const cols = new Set();
        for (const fk of foreignKeys) {
            fk.columns.forEach(c => cols.add(c));
        }
        return cols;
    },
};


// ═══════════════════════════════════════════════════════════════════
// 3. NODE FACTORY
//    Registers the custom X6 node shape and builds table nodes.
// ═══════════════════════════════════════════════════════════════════

const NodeFactory = {
    register() {
        X6.Graph.registerNode('db-table', {
            markup: [{ tagName: 'foreignObject', selector: 'fo' }],
            attrs: { fo: { refWidth: '100%', refHeight: '100%', x: 0, y: 0 } },
        }, true);
    },

    nodeHeight(table) {
        return CONFIG.node.headerH + table.columns.length * CONFIG.node.rowH;
    },

    buildHtml(table) {
        const icon = `<svg width="10" height="10" viewBox="0 0 10 10"><rect width="10" height="10" rx="2" fill="rgba(255,255,255,0.35)"/></svg>`;

        const rows = table.columns.map(col => {
            const pk   = col.isPk   ? '<span class="tn-pk">PK</span>'   : '';
            const fk   = col.isFk   ? '<span class="tn-fk">FK</span>'   : '';
            const nil  = col.nullable ? '<span class="tn-null">○</span>' : '';

            return `<div class="tn-row">
                        ${pk}${fk}
                        <span class="tn-name">${esc(col.name)}</span>
                        <span class="tn-type">${esc(col.type)}</span>
                        ${nil}
                    </div>`;
        }).join('');

        return `<div xmlns="http://www.w3.org/1999/xhtml" class="tn">
                    <div class="tn-head" style="background:${table.color}">${icon}${esc(table.name)}</div>
                    ${rows}
                </div>`;
    },

    // Ports sit at the vertical midpoint of each column row,
    // on both left (in) and right (out) sides.
    buildPorts(table) {
        const items = [];
        table.columns.forEach((col, idx) => {
            const y = CONFIG.node.headerH + idx * CONFIG.node.rowH + CONFIG.node.rowH / 2;
            items.push(
                { id: `${col.name}:in`,  args: { x: 0,                  y }, attrs: { circle: { r: 0 } } },
                { id: `${col.name}:out`, args: { x: CONFIG.node.width,   y }, attrs: { circle: { r: 0 } } },
            );
        });
        return { groups: {}, items };
    },

    create(graph, table, pos) {
        const node = graph.addNode({
            id:    table.name,
            shape: 'db-table',
            x: pos.x,
            y: pos.y,
            width:  CONFIG.node.width,
            height: this.nodeHeight(table),
            ports:  this.buildPorts(table),
            data:   { html: this.buildHtml(table) },
        });

        // Inject HTML into the foreignObject after X6 renders the SVG element
        requestAnimationFrame(() => {
            const view = graph.findViewByCell(node);
            const fo   = view?.container?.querySelector('foreignObject');
            if (fo) fo.innerHTML = table._html ?? node.getData().html;
        });

        return node;
    },
};


// ═══════════════════════════════════════════════════════════════════
// 4. EDGE FACTORY
//    Draws foreign-key relationships as edges between table nodes.
// ═══════════════════════════════════════════════════════════════════

const EdgeFactory = {
    create(graph, table) {
        for (const fk of table.foreignKeys) {
            if (!graph.hasCell(fk.targetTable)) continue;

            const sourcePort = fk.sourceCol ? `${fk.sourceCol}:out` : undefined;
            const targetPort = fk.targetCol ? `${fk.targetCol}:in`  : undefined;

            graph.addEdge({
                source: { cell: table.name,    ...(sourcePort ? { port: sourcePort } : {}) },
                target: { cell: fk.targetTable, ...(targetPort ? { port: targetPort } : {}) },
                attrs: {
                    line: {
                        stroke: '#94a3b8',
                        strokeWidth: 1.5,
                        targetMarker: { name: 'classic', size: 7, fill: '#94a3b8' },
                    },
                },
                router:    { name: 'manhattan', args: { padding: 20 } },
                connector: { name: 'rounded',   args: { radius: 6 } },
            });
        }
    },
};


// ═══════════════════════════════════════════════════════════════════
// 5. LAYOUT ENGINE
//    Arranges table nodes in a grid, independent tables first.
// ═══════════════════════════════════════════════════════════════════

const LayoutEngine = {
    compute(tables) {
        const ordered  = this.sortedTables(tables);
        const positions = {};

        let col = 0, x = 0, y = 0, rowMaxH = 0;

        for (const table of ordered) {
            positions[table.name] = { x, y };

            rowMaxH = Math.max(rowMaxH, NodeFactory.nodeHeight(table));
            col++;

            if (col >= CONFIG.layout.cols) {
                col = 0;
                x = 0;
                y += rowMaxH + CONFIG.layout.vGap;
                rowMaxH = 0;
            } else {
                x += CONFIG.node.width + CONFIG.layout.hGap;
            }
        }

        return positions;
    },

    // Tables with no outgoing FKs are placed first (they tend to be
    // lookup / reference tables and look cleaner on the left).
    sortedTables(tables) {
        const withFk    = tables.filter(t => t.foreignKeys.length > 0);
        const withoutFk = tables.filter(t => t.foreignKeys.length === 0);
        return [...withoutFk, ...withFk];
    },
};


// ═══════════════════════════════════════════════════════════════════
// 6. GRAPH MANAGER
//    Owns the X6 graph instance and provides high-level operations.
// ═══════════════════════════════════════════════════════════════════

const GraphManager = {
    graph: null,

    init(tables) {
        NodeFactory.register();

        this.graph = new X6.Graph({
            container: document.getElementById('container'),
            autoResize: true,
            grid: { visible: true, type: 'dot', args: { color: '#cbd5e1', thickness: 1 } },
            background: { color: '#f1f5f9' },
            panning:    { enabled: true, modifiers: null },
            mousewheel: { enabled: true, zoomAtMousePosition: true, minScale: 0.2, maxScale: 3 },
            interacting: { nodeMovable: true, edgeMovable: false, edgeLabelMovable: false },
        });

        const positions = LayoutEngine.compute(tables);
        tables.forEach(t  => NodeFactory.create(this.graph, t, positions[t.name]));
        tables.forEach(t  => EdgeFactory.create(this.graph, t));

        // Fit after the first render pass so all foreignObject HTML is in place
        setTimeout(() => this.graph.fitContent({ padding: 40, maxScale: 1 }), 50);

        this.graph.on('scale', ({ sx }) => {
            document.getElementById('zoom-label').textContent =
                Math.round(sx * 100) + '%';
        });

        return this.graph;
    },

    zoomIn()  { this.graph.zoom(0.1); },
    zoomOut() { this.graph.zoom(-0.1); },

    fitView() { this.graph.fitContent({ padding: 40, maxScale: 1 }); },

    focusCell(name) {
        const cell = this.graph.getCellById(name);
        if (!cell) return;
        this.graph.centerCell(cell);
        if (this.graph.zoom() < 0.6) this.graph.zoomTo(0.8);
    },
};


// ═══════════════════════════════════════════════════════════════════
// 7. SIDEBAR MANAGER
//    Renders the table list, handles search and active state.
// ═══════════════════════════════════════════════════════════════════

const SidebarManager = {
    tables:      [],
    activeTable: null,

    init(tables) {
        this.tables = tables;
        document.getElementById('table-count').textContent =
            `${tables.length} table${tables.length !== 1 ? 's' : ''}`;
        this.render();
        this.setupSearch();
    },

    render(filter = '') {
        const q     = filter.toLowerCase();
        const items = q
            ? this.tables.filter(t => t.name.toLowerCase().includes(q))
            : this.tables;

        const list = document.getElementById('table-list');
        list.innerHTML = items.map(t => `
            <div class="table-item ${t.name === this.activeTable ? 'active' : ''}"
                 data-table="${esc(t.name)}" title="${esc(t.name)}">
                <svg width="10" height="10" viewBox="0 0 10 10">
                    <rect width="10" height="10" rx="2" fill="${t.color}"/>
                </svg>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0">
                    ${esc(t.name)}
                </span>
                <span class="badge">${t.columns.length}</span>
            </div>
        `).join('');

        list.querySelectorAll('.table-item').forEach(el => {
            el.addEventListener('click', () => {
                const name = el.dataset.table;
                this.activeTable = name;
                this.render(document.getElementById('search').value);
                GraphManager.focusCell(name);
            });
        });
    },

    setupSearch() {
        document.getElementById('search').addEventListener('input', e => {
            this.render(e.target.value);
        });
    },
};


// ═══════════════════════════════════════════════════════════════════
// 8. TOOLBAR
// ═══════════════════════════════════════════════════════════════════

function setupToolbar() {
    document.getElementById('btn-zoom-in') .addEventListener('click', () => GraphManager.zoomIn());
    document.getElementById('btn-zoom-out').addEventListener('click', () => GraphManager.zoomOut());
    document.getElementById('btn-fit')     .addEventListener('click', () => GraphManager.fitView());
}


// ═══════════════════════════════════════════════════════════════════
// 9. UTILITIES
// ═══════════════════════════════════════════════════════════════════

function esc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}


// ═══════════════════════════════════════════════════════════════════
// 10. BOOTSTRAP
// ═══════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    const tables = DataProcessor.process(RAW_TABLES);

    SidebarManager.init(tables);
    GraphManager.init(tables);
    setupToolbar();
});
</script>
</body>
</html>
