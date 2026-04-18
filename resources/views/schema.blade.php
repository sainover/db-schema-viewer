<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DB Schema Viewer</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        header {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-shrink: 0;
        }

        header h1 { font-size: 1.125rem; font-weight: 600; color: #f1f5f9; }

        .stats { display: flex; gap: 0.75rem; }

        .stat {
            font-size: 0.75rem;
            color: #94a3b8;
            background: #0f172a;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid #334155;
        }

        .stat span { color: #38bdf8; font-weight: 600; }

        #viewport {
            flex: 1;
            overflow: hidden;
            position: relative;
            cursor: grab;
            user-select: none;
        }

        #viewport.dragging { cursor: grabbing; }

        #pan-layer {
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: 0 0;
        }

        #controls {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            z-index: 10;
        }

        .ctrl-btn {
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.125rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }

        .ctrl-btn:hover { background: #334155; }

        #error-box {
            display: none;
            background: #450a0a;
            border: 1px solid #7f1d1d;
            border-radius: 8px;
            padding: 1rem;
            color: #fca5a5;
            font-family: monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
            margin: 2rem;
            max-width: 700px;
        }
    </style>
</head>
<body>

@php
    $pkMap = [];
    $fkMap = [];
    $relationshipCount = 0;

    foreach ($tables as $tableName => $tableData) {
        $pkMap[$tableName] = [];
        $fkMap[$tableName] = [];

        foreach ($tableData['indexes'] as $index) {
            if (!empty($index['primary'])) {
                $pkMap[$tableName] = array_merge($pkMap[$tableName], $index['columns']);
            }
        }

        foreach ($tableData['foreign_keys'] as $fk) {
            $fkMap[$tableName] = array_merge($fkMap[$tableName], $fk['columns']);
            $relationshipCount++;
        }
    }

    // Infer relations from _id columns when no FK constraints are defined
    $tableNames   = array_keys($tables);
    $inferredRels = [];

    foreach ($tables as $tableName => $tableData) {
        foreach ($tableData['columns'] as $column) {
            $colName = $column['name'];
            if (!str_ends_with($colName, '_id')) {
                continue;
            }

            $prefix = substr($colName, 0, -3);

            $candidates = [$prefix, $prefix . 's', $prefix . 'es'];
            if (str_ends_with($prefix, 'y')) {
                $candidates[] = substr($prefix, 0, -1) . 'ies';
            }

            foreach ($candidates as $candidate) {
                if (in_array($candidate, $tableNames, true) && $candidate !== $tableName) {
                    $inferredRels[] = ['child' => $tableName, 'parent' => $candidate, 'column' => $colName];
                    $fkMap[$tableName][] = $colName;
                    $relationshipCount++;
                    break;
                }
            }
        }
    }

    $sanitizeName = fn(string $name): string =>
        preg_match('/[^a-zA-Z0-9_]/', $name) ? '"' . addslashes($name) . '"' : $name;

    $sanitizeType = fn(?string $type): string =>
        preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($type ?? 'unknown')) ?: 'unknown';

    $lines = ['erDiagram'];

    foreach ($tables as $tableName => $tableData) {
        $lines[] = '    ' . $sanitizeName($tableName) . ' {';
        foreach ($tableData['columns'] as $column) {
            $type = $sanitizeType($column['type_name'] ?? null);
            $safeColName = preg_replace('/[^a-zA-Z0-9_]/', '_', $column['name']);
            $marker = '';
            if (in_array($column['name'], $pkMap[$tableName])) {
                $marker = ' PK';
            } elseif (in_array($column['name'], $fkMap[$tableName])) {
                $marker = ' FK';
            }
            $lines[] = "        {$type} {$safeColName}{$marker}";
        }
        $lines[] = '    }';
    }

    foreach ($tables as $tableName => $tableData) {
        foreach ($tableData['foreign_keys'] as $fk) {
            $child  = $sanitizeName($tableName);
            $parent = $sanitizeName($fk['foreign_table']);
            $label  = preg_replace('/[^a-zA-Z0-9_]/', '_', $fk['name']);
            $lines[] = "    {$child} }o--|| {$parent} : \"{$label}\"";
        }
    }

    foreach ($inferredRels as $rel) {
        $child  = $sanitizeName($rel['child']);
        $parent = $sanitizeName($rel['parent']);
        $lines[] = "    {$child} }o--|| {$parent} : \"{$rel['column']}\"";
    }

    $diagramDef = implode("\n", $lines);
@endphp

<header>
    <h1>DB Schema Viewer</h1>
    <div class="stats">
        <div class="stat">Tables: <span>{{ count($tables) }}</span></div>
        <div class="stat">Relationships: <span>{{ $relationshipCount }}</span></div>
    </div>
</header>

<div id="viewport">
    <div id="pan-layer">
        <div id="diagram-container"></div>
        <div id="error-box"></div>
    </div>
</div>

<div id="controls">
    <button class="ctrl-btn" id="btn-zoom-in"  title="Zoom in">+</button>
    <button class="ctrl-btn" id="btn-reset"    title="Reset">&#8635;</button>
    <button class="ctrl-btn" id="btn-zoom-out" title="Zoom out">&#8722;</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({
        startOnLoad: false,
        theme: 'dark',
        er: { diagramPadding: 30, layoutDirection: 'TB', minEntityWidth: 100, minEntityHeight: 75, entityPadding: 15 },
    });

    const viewport = document.getElementById('viewport');
    const panLayer = document.getElementById('pan-layer');

    let scale = 1, tx = 0, ty = 0;

    function applyTransform() {
        panLayer.style.transform = `translate(${tx}px, ${ty}px) scale(${scale})`;
    }

    function fitToViewport() {
        const svg = panLayer.querySelector('svg');
        if (!svg) return;
        const vb = svg.viewBox.baseVal;
        const sw = vb.width  || svg.width.baseVal.value  || svg.getBoundingClientRect().width;
        const sh = vb.height || svg.height.baseVal.value || svg.getBoundingClientRect().height;
        if (!sw || !sh) return;
        const vw = viewport.clientWidth;
        const vh = viewport.clientHeight;
        const padding = 48;
        scale = Math.min((vw - padding) / sw, (vh - padding) / sh);
        tx = (vw - sw * scale) / 2;
        ty = (vh - sh * scale) / 2;
        applyTransform();
    }

    // Wheel zoom toward cursor
    viewport.addEventListener('wheel', e => {
        e.preventDefault();
        const rect = viewport.getBoundingClientRect();
        const mx = e.clientX - rect.left;
        const my = e.clientY - rect.top;
        const factor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
        tx = mx - (mx - tx) * factor;
        ty = my - (my - ty) * factor;
        scale = Math.max(0.05, Math.min(scale * factor, 8));
        applyTransform();
    }, { passive: false });

    // Drag pan
    let dragging = false, startX, startY;

    viewport.addEventListener('mousedown', e => {
        dragging = true;
        startX = e.clientX - tx;
        startY = e.clientY - ty;
        viewport.classList.add('dragging');
    });

    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        tx = e.clientX - startX;
        ty = e.clientY - startY;
        applyTransform();
    });

    document.addEventListener('mouseup', () => {
        dragging = false;
        viewport.classList.remove('dragging');
    });

    // Buttons
    document.getElementById('btn-zoom-in').addEventListener('click', () => {
        const cx = viewport.clientWidth / 2, cy = viewport.clientHeight / 2;
        const f = 1.25;
        tx = cx - (cx - tx) * f;
        ty = cy - (cy - ty) * f;
        scale = Math.min(scale * f, 8);
        applyTransform();
    });

    document.getElementById('btn-zoom-out').addEventListener('click', () => {
        const cx = viewport.clientWidth / 2, cy = viewport.clientHeight / 2;
        const f = 1 / 1.25;
        tx = cx - (cx - tx) * f;
        ty = cy - (cy - ty) * f;
        scale = Math.max(scale * f, 0.05);
        applyTransform();
    });

    document.getElementById('btn-reset').addEventListener('click', fitToViewport);

    // Render
    const diagramDef = @json($diagramDef);

    mermaid.render('schema-svg', diagramDef)
        .then(({ svg }) => {
            document.getElementById('diagram-container').innerHTML = svg;
            fitToViewport();
        })
        .catch(err => {
            const errBox = document.getElementById('error-box');
            errBox.style.display = 'block';
            errBox.textContent = 'Mermaid render error:\n' + err.message + '\n\nDiagram definition:\n' + diagramDef;
        });
</script>
</body>
</html>
