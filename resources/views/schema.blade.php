<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DB Schema Viewer</title>
    <script src="https://cdn.jsdelivr.net/npm/@antv/x6/dist/index.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0f1117;
            --bg-surface: #181c27;
            --bg-card: #1e2235;
            --bg-hover: #252a40;
            --border: #2d3352;
            --border-light: #3a4060;
            --text: #e8eaf0;
            --text-muted: #7c82a0;
            --text-dim: #4a5070;
            --accent: #5b7cf6;
            --accent-hover: #7090ff;
            --accent-dim: #1e2a50;
            --success: #34d399;
            --warning: #fbbf24;
            --danger: #f87171;
            --pk-color: #fbbf24;
            --fk-color: #60a5fa;
            --edge-color: #3a4a7a;
            --font: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Toolbar ── */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 16px;
            height: 52px;
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            z-index: 100;
        }

        .toolbar-brand {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--accent);
            text-transform: uppercase;
            margin-right: 8px;
        }

        .toolbar-sep {
            width: 1px;
            height: 24px;
            background: var(--border);
            margin: 0 4px;
        }

        .btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-family: var(--font);
            font-size: 12px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn:hover { background: var(--bg-hover); color: var(--text); border-color: var(--border-light); }
        .btn.primary { background: var(--accent-dim); color: var(--accent); border-color: var(--accent); }
        .btn.primary:hover { background: var(--accent); color: #fff; }
        .btn.danger-btn { color: var(--danger); border-color: #4a2a2a; }
        .btn.danger-btn:hover { background: #2a1515; }

        .toolbar-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }

        /* Layout selector */
        .layout-select {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            font-family: var(--font);
            font-size: 12px;
            background: var(--bg-card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            outline: none;
        }
        .layout-select:focus { border-color: var(--accent); }

        /* Dirty indicator */
        .dirty-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--warning);
            display: none;
            flex-shrink: 0;
        }
        .dirty-dot.visible { display: block; }

        /* ── Main area ── */
        .main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* ── Canvas ── */
        #graph-container {
            flex: 1;
            background:
                radial-gradient(circle at 50% 50%, #1a1f35 0%, var(--bg) 70%);
            background-size: 24px 24px;
            background-image:
                radial-gradient(circle at 50% 50%, #1a1f35 0%, var(--bg) 70%),
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            position: relative;
            overflow: hidden;
        }

        /* Dot grid overlay */
        #graph-container::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, #2d3352 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Sidebar (details panel) ── */
        .sidebar {
            width: 280px;
            background: var(--bg-surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow: hidden;
            transition: width 0.2s ease;
        }
        .sidebar.collapsed { width: 0; border-left: none; }

        .sidebar-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sidebar-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-dim);
        }
        .sidebar-table-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--accent);
            margin-top: 4px;
        }

        .sidebar-body { flex: 1; overflow-y: auto; padding: 12px; }

        .comment-label {
            font-size: 11px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .comment-textarea {
            width: 100%;
            min-height: 80px;
            padding: 8px 10px;
            font-family: var(--font);
            font-size: 12px;
            background: var(--bg-card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            resize: vertical;
            outline: none;
            transition: border-color 0.15s;
            margin-bottom: 12px;
        }
        .comment-textarea:focus { border-color: var(--accent); }

        .col-list { display: flex; flex-direction: column; gap: 2px; }
        .col-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.1s;
        }
        .col-item:hover { background: var(--bg-hover); }
        .col-item.selected { background: var(--accent-dim); }
        .col-name { font-size: 12px; color: var(--text); flex: 1; }
        .col-type { font-size: 11px; color: var(--text-dim); }
        .col-badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 3px; }
        .badge {
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .badge-pk { background: #2d2510; color: var(--pk-color); }
        .badge-fk { background: #0f1e30; color: var(--fk-color); }
        .badge-null { background: #1a2010; color: #6ee7b7; }
        .badge-unique { background: #1e1535; color: #a78bfa; }

        .col-comment-input {
            width: 100%;
            padding: 5px 8px;
            font-family: var(--font);
            font-size: 11px;
            background: var(--bg);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 4px;
            outline: none;
            margin-top: 6px;
            display: none;
        }
        .col-comment-input:focus { border-color: var(--accent); color: var(--text); display: block; }
        .col-item.selected .col-comment-input { display: block; }

        /* ── Save layout modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            display: none;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 10px;
            padding: 24px;
            width: 320px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .modal-title { font-size: 14px; font-weight: 700; color: var(--text); }
        .modal-input {
            width: 100%;
            padding: 8px 12px;
            font-family: var(--font);
            font-size: 13px;
            background: var(--bg-card);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            outline: none;
        }
        .modal-input:focus { border-color: var(--accent); }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            color: var(--text);
            font-size: 12px;
            padding: 8px 16px;
            border-radius: 6px;
            opacity: 0;
            transition: all 0.25s;
            z-index: 9999;
            white-space: nowrap;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 3px; }

        /* Icon helpers */
        .icon { width: 14px; height: 14px; display: inline-block; flex-shrink: 0; }
    </style>
</head>
<body>

{{-- ── Toolbar ── --}}
<div class="toolbar">
    <span class="toolbar-brand">Schema</span>

    <div class="toolbar-sep"></div>

    <select class="layout-select" id="layout-select" onchange="loadLayout(this.value)">
        <option value="">— select layout —</option>
        @foreach($layouts as $layout)
            <option value="{{ $layout['slug'] }}" {{ $layout['is_default'] ? 'selected' : '' }}>
                {{ $layout['name'] }}
            </option>
        @endforeach
    </select>

    <div class="dirty-dot" id="dirty-dot" title="Unsaved local changes"></div>

    <div class="toolbar-sep"></div>

    <button class="btn" onclick="autoLayout()">
        <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="1" y="1" width="6" height="4" rx="1"/><rect x="9" y="1" width="6" height="4" rx="1"/>
            <rect x="1" y="10" width="6" height="5" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/>
        </svg>
        Auto layout
    </button>

    <button class="btn" onclick="fitView()">
        <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M1 5V1h4M11 1h4v4M15 11v4h-4M5 15H1v-4"/>
        </svg>
        Fit
    </button>

    <div class="toolbar-right">
        <button class="btn" onclick="saveToLocal()">
            <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M13 13H3a1 1 0 01-1-1V4l3-3h8a1 1 0 011 1v10a1 1 0 01-1 1z"/>
                <path d="M5 1v4h6V1M5 13v-4h6v4"/>
            </svg>
            Save local
        </button>

        <button class="btn primary" onclick="openSaveModal()">
            <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 1v10M4 7l4 4 4-4M2 13h12"/>
            </svg>
            Save to project
        </button>
    </div>
</div>

{{-- ── Main ── --}}
<div class="main">
    <div id="graph-container"></div>

    {{-- Sidebar --}}
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-header">
            <div>
                <div class="sidebar-title">Table details</div>
                <div class="sidebar-table-name" id="sidebar-table-name">—</div>
            </div>
            <button class="btn" onclick="closeSidebar()" style="padding:4px 8px;">✕</button>
        </div>
        <div class="sidebar-body" id="sidebar-body"></div>
    </div>
</div>

{{-- ── Save modal ── --}}
<div class="modal-overlay" id="save-modal">
    <div class="modal">
        <div class="modal-title">Save layout to project</div>
        <input class="modal-input" id="save-name" type="text" placeholder="Layout name (e.g. Auth module)" />
        <div class="modal-actions">
            <button class="btn" onclick="closeSaveModal()">Cancel</button>
            <button class="btn primary" onclick="saveToProject()">Save</button>
        </div>
    </div>
</div>

{{-- ── Toast ── --}}
<div class="toast" id="toast"></div>

<script>
// ── PHP data passed to JS ──────────────────────────────────────────────
const TABLES = @json($tables);
const LAYOUTS = @json($layouts);
const ROUTES = {
    layouts:    '{{ route("db-schema-viewer.layouts.index") }}',
    store:      '{{ route("db-schema-viewer.layouts.store") }}',
    update:     (id) => `{{ url("db-schema-viewer/layouts") }}/${id}`,
};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── State ─────────────────────────────────────────────────────────────
let graph = null;
let currentLayoutSlug = null;
let selectedTable = null;
let selectedCol = null;
let isDirty = false;

// positions, comments loaded from localStorage / server
let state = { positions: {}, comments: {}, columnComments: {} };

// ── Graph init ────────────────────────────────────────────────────────
function initGraph() {
    graph = new X6.Graph({
        container: document.getElementById('graph-container'),
        grid: false,
        background: false,
        mousewheel: { enabled: true, modifiers: null, factor: 1.08 },
        panning: { enabled: true, modifiers: null },
        selecting: { enabled: false },
        connecting: { enabled: false },
        interacting: (cellView) => {
            if (cellView.cell.isNode()) return { nodeMovable: true };
            return false;
        },
    });

    graph.on('node:moved', ({ cell }) => {
        const pos = cell.getPosition();
        state.positions[cell.id] = { x: pos.x, y: pos.y };
        markDirty();
        saveToLocalSilent();
    });

    graph.on('node:click', ({ cell }) => {
        openSidebar(cell.id);
    });

    graph.on('blank:click', () => {
        closeSidebar();
    });
}

// ── Node / edge builders ──────────────────────────────────────────────
function buildTableNode(name, columns, pos) {
    const pkCols = columns.filter(c => c.primary);
    const fkNames = new Set(
        (TABLES[name]?.foreign_keys || []).map(fk => fk.columns[0])
    );
    const uniqueNames = new Set(
        (TABLES[name]?.indexes || [])
            .filter(i => i.unique && !i.primary)
            .flatMap(i => i.columns)
    );

    const rowH = 26;
    const headerH = 36;
    const height = headerH + columns.length * rowH + 8;
    const width = 220;

    // Build HTML for the node
    const colRows = columns.map(col => {
        const isPk = col.primary;
        const isFk = fkNames.has(col.name);
        const isUnique = uniqueNames.has(col.name);
        const isNull = col.nullable;

        const badges = [
            isPk ? `<span style="font-size:9px;padding:1px 4px;background:#2d2510;color:#fbbf24;border-radius:2px;font-weight:700">PK</span>` : '',
            isFk ? `<span style="font-size:9px;padding:1px 4px;background:#0f1e30;color:#60a5fa;border-radius:2px;font-weight:700">FK</span>` : '',
            isUnique ? `<span style="font-size:9px;padding:1px 4px;background:#1e1535;color:#a78bfa;border-radius:2px;font-weight:700">UQ</span>` : '',
        ].join('');

        return `
            <div style="display:flex;align-items:center;gap:6px;padding:0 10px;height:${rowH}px;border-top:1px solid #2d3352;">
                <span style="font-size:11px;color:${isPk ? '#fbbf24' : isFk ? '#60a5fa' : '#c8cadc'};flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${col.name}</span>
                <span style="font-size:10px;color:#4a5070;flex-shrink:0">${col.type_name || col.type}</span>
                ${badges}
                ${isNull ? `<span style="font-size:9px;color:#4a5070">?</span>` : ''}
            </div>`;
    }).join('');

    const html = `
        <div style="font-family:'JetBrains Mono',monospace;background:#1e2235;border:1px solid #2d3352;border-radius:8px;overflow:hidden;width:${width}px;box-shadow:0 4px 24px rgba(0,0,0,0.4)">
            <div style="padding:0 10px;height:${headerH}px;display:flex;align-items:center;background:#252a40;border-bottom:1px solid #3a4060;gap:6px">
                <span style="width:8px;height:8px;border-radius:50%;background:#5b7cf6;flex-shrink:0"></span>
                <span style="font-size:13px;font-weight:700;color:#e8eaf0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${name}</span>
                <span style="margin-left:auto;font-size:10px;color:#4a5070">${columns.length} cols</span>
            </div>
            ${colRows}
        </div>`;

    return {
        id: name,
        shape: 'html',
        x: pos.x,
        y: pos.y,
        width,
        height,
        html,
    };
}

function buildEdges(tables) {
    const edges = [];
    for (const [tableName, data] of Object.entries(tables)) {
        for (const fk of (data.foreign_keys || [])) {
            edges.push({
                source: { cell: tableName, port: fk.columns[0] },
                target: { cell: fk.foreign_table },
                attrs: {
                    line: {
                        stroke: '#3a4a7a',
                        strokeWidth: 1.5,
                        targetMarker: { name: 'block', size: 6 },
                    },
                },
                router: { name: 'manhattan', args: { padding: 20 } },
                connector: { name: 'rounded', args: { radius: 8 } },
            });
        }
    }
    return edges;
}

// ── Render graph ──────────────────────────────────────────────────────
function renderGraph() {
    graph.clearCells();
    const names = Object.keys(TABLES);

    // Auto-grid positions for tables without stored positions
    const cols = Math.ceil(Math.sqrt(names.length));
    const gapX = 260, gapY = 320;

    const nodes = names.map((name, i) => {
        const storedPos = state.positions[name];
        const pos = storedPos || {
            x: 60 + (i % cols) * gapX,
            y: 60 + Math.floor(i / cols) * gapY,
        };
        return buildTableNode(name, TABLES[name].columns, pos);
    });

    const edges = buildEdges(TABLES);
    graph.addNodes(nodes);
    graph.addEdges(edges);
}

// ── Fit / layout ──────────────────────────────────────────────────────
function fitView() {
    graph.zoomToFit({ padding: 40 });
}

function autoLayout() {
    const names = Object.keys(TABLES);
    const cols = Math.ceil(Math.sqrt(names.length));
    const gapX = 260, gapY = 320;
    names.forEach((name, i) => {
        const node = graph.getCellById(name);
        if (!node) return;
        const pos = { x: 60 + (i % cols) * gapX, y: 60 + Math.floor(i / cols) * gapY };
        node.setPosition(pos);
        state.positions[name] = pos;
    });
    markDirty();
    saveToLocalSilent();
    fitView();
}

// ── Sidebar ───────────────────────────────────────────────────────────
function openSidebar(tableName) {
    selectedTable = tableName;
    selectedCol = null;
    document.getElementById('sidebar').classList.remove('collapsed');
    document.getElementById('sidebar-table-name').textContent = tableName;
    renderSidebarBody(tableName);
}

function closeSidebar() {
    selectedTable = null;
    document.getElementById('sidebar').classList.add('collapsed');
}

function renderSidebarBody(tableName) {
    const data = TABLES[tableName];
    const tableComment = state.comments[tableName] || '';
    const colComments = state.columnComments || {};

    const colItems = data.columns.map(col => {
        const colKey = `${tableName}.${col.name}`;
        const comment = colComments[colKey] || '';
        return `
            <div class="col-item ${selectedCol === col.name ? 'selected' : ''}" onclick="selectCol('${col.name}', '${tableName}')">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:6px">
                        <span class="col-name">${col.name}</span>
                        <span class="col-type">${col.type_name || col.type}</span>
                    </div>
                    <div class="col-badges">
                        ${col.primary ? '<span class="badge badge-pk">PK</span>' : ''}
                        ${col.nullable ? '<span class="badge badge-null">null</span>' : ''}
                    </div>
                    <input class="col-comment-input"
                        id="col-comment-${col.name}"
                        value="${escapeHtml(comment)}"
                        placeholder="Add note…"
                        onchange="saveColComment('${tableName}', '${col.name}', this.value)"
                    />
                </div>
            </div>`;
    }).join('');

    document.getElementById('sidebar-body').innerHTML = `
        <div class="comment-label">Table note</div>
        <textarea class="comment-textarea"
            placeholder="Describe this table…"
            onchange="saveTableComment('${tableName}', this.value)"
        >${escapeHtml(tableComment)}</textarea>

        <div class="comment-label" style="margin-bottom:8px">Columns</div>
        <div class="col-list">${colItems}</div>
    `;
}

function selectCol(colName, tableName) {
    selectedCol = colName;
    renderSidebarBody(tableName);
    const el = document.getElementById(`col-comment-${colName}`);
    if (el) el.focus();
}

function saveTableComment(tableName, val) {
    state.comments[tableName] = val;
    markDirty();
    saveToLocalSilent();
}

function saveColComment(tableName, colName, val) {
    if (!state.columnComments) state.columnComments = {};
    state.columnComments[`${tableName}.${colName}`] = val;
    markDirty();
    saveToLocalSilent();
}

// ── Dirty state ───────────────────────────────────────────────────────
function markDirty() {
    isDirty = true;
    document.getElementById('dirty-dot').classList.add('visible');
}

function clearDirty() {
    isDirty = false;
    document.getElementById('dirty-dot').classList.remove('visible');
}

// ── LocalStorage ──────────────────────────────────────────────────────
function localKey() {
    return `db-schema-viewer:${currentLayoutSlug || '__default__'}`;
}

function saveToLocalSilent() {
    localStorage.setItem(localKey(), JSON.stringify({ ...state, _savedAt: Date.now() }));
}

function saveToLocal() {
    saveToLocalSilent();
    clearDirty();
    showToast('Saved to local storage');
}

function loadFromLocal() {
    try {
        const raw = localStorage.getItem(localKey());
        return raw ? JSON.parse(raw) : null;
    } catch { return null; }
}

// ── Server persistence ────────────────────────────────────────────────
async function loadLayout(slug) {
    currentLayoutSlug = slug || null;

    // 1. Try localStorage first
    const local = loadFromLocal();
    if (local) {
        state = { positions: local.positions || {}, comments: local.comments || {}, columnComments: local.columnComments || {} };
        if (local._dirty) markDirty();
    } else if (slug) {
        // 2. Fetch from server
        try {
            const res = await fetch(`${ROUTES.layouts}/${slug}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                state = { positions: data.positions || {}, comments: data.comments || {}, columnComments: data.column_comments || {} };
            }
        } catch {}
    }

    renderGraph();
    if (!slug) fitView();
}

// ── Save to project ───────────────────────────────────────────────────
function openSaveModal() {
    document.getElementById('save-modal').classList.add('open');
    document.getElementById('save-name').focus();
}

function closeSaveModal() {
    document.getElementById('save-modal').classList.remove('open');
}

async function saveToProject() {
    const name = document.getElementById('save-name').value.trim();
    if (!name) return;

    const payload = {
        name,
        positions: state.positions,
        comments: state.comments,
        column_comments: state.columnComments,
    };

    try {
        const url = currentLayoutSlug ? ROUTES.update(currentLayoutSlug) : ROUTES.store;
        const method = currentLayoutSlug ? 'PUT' : 'POST';
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        currentLayoutSlug = data.slug;
        clearDirty();
        closeSaveModal();
        showToast('Layout saved to project');
    } catch {
        showToast('Failed to save — check your connection');
    }
}

// ── Toast ─────────────────────────────────────────────────────────────
function showToast(msg) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2500);
}

// ── Utils ─────────────────────────────────────────────────────────────
function escapeHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Boot ──────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    initGraph();

    // Load default layout if set
    const defaultLayout = LAYOUTS.find(l => l.is_default);
    if (defaultLayout) {
        document.getElementById('layout-select').value = defaultLayout.slug;
        loadLayout(defaultLayout.slug);
    } else {
        loadLayout(null);
        setTimeout(fitView, 100);
    }

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) e.preventDefault();
    });

    // Close modal on overlay click
    document.getElementById('save-modal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeSaveModal();
    });

    // Save-name enter key
    document.getElementById('save-name').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') saveToProject();
    });
});
</script>
</body>
</html>
