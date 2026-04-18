<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DB Schema Viewer</title>
    <script src="https://cdn.jsdelivr.net/npm/@antv/x6/dist/index.js"></script>
</head>
<body>
    <div id="container" style="width: 100%; height: 100vh; border: 1px solid #ccc;"></div>
    <script>
        const graph = new X6.Graph({
            container: document.getElementById('container'),
            width: '100%',
            height: '100%',
            grid: true,
        });

        const tables = @json($tables);
    </script>
</body>
</html>
