<?php

namespace Sainover\DbSchemaViewer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class LayoutController extends Controller
{
    private string $dir;

    public function __construct()
    {
        $this->dir = base_path('.db-schema');

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    /**
     * GET /db-schema-viewer/layouts
     * List all saved layouts.
     */
    public function index(): JsonResponse
    {
        $files = glob("{$this->dir}/*.json") ?: [];

        $layouts = collect($files)
            ->map(fn (string $path) => $this->readFile($path))
            ->filter()
            ->sortBy('name')
            ->values();

        return response()->json($layouts);
    }

    /**
     * GET /db-schema-viewer/layouts/{slug}
     * Return a single layout.
     */
    public function show(string $slug): JsonResponse
    {
        $path = $this->path($slug);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Layout not found'], 404);
        }

        return response()->json($this->readFile($path));
    }

    /**
     * POST /db-schema-viewer/layouts
     * Create a new layout.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                     => 'required|string|max:100',
            'positions'                => 'nullable|array',
            'comments'                 => 'nullable|array',
            'column_comments'          => 'nullable|array',
            'is_default'               => 'nullable|boolean',
        ]);

        $slug = $this->uniqueSlug($data['name']);

        if (! empty($data['is_default'])) {
            $this->clearDefault();
        }

        $payload = $this->buildPayload($slug, $data);
        $this->writeFile($slug, $payload);

        return response()->json($payload, 201);
    }

    /**
     * PUT /db-schema-viewer/layouts/{slug}
     * Update an existing layout.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $path = $this->path($slug);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Layout not found'], 404);
        }

        $data = $request->validate([
            'name'                     => 'sometimes|string|max:100',
            'positions'                => 'nullable|array',
            'comments'                 => 'nullable|array',
            'column_comments'          => 'nullable|array',
            'is_default'               => 'nullable|boolean',
        ]);

        $existing = $this->readFile($path);

        // If name changed — rename file, keep old slug for backwards compat
        if (isset($data['name']) && $data['name'] !== $existing['name']) {
            $newSlug = $this->uniqueSlug($data['name'], $slug);
            if ($newSlug !== $slug) {
                rename($path, $this->path($newSlug));
                $slug = $newSlug;
            }
        }

        if (! empty($data['is_default'])) {
            $this->clearDefault();
        }

        $payload = array_merge($existing, $this->buildPayload($slug, array_merge($existing, $data)));
        $this->writeFile($slug, $payload);

        return response()->json($payload);
    }

    /**
     * DELETE /db-schema-viewer/layouts/{slug}
     * Delete a layout file.
     */
    public function destroy(string $slug): JsonResponse
    {
        $path = $this->path($slug);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Layout not found'], 404);
        }

        unlink($path);

        return response()->json(['deleted' => true]);
    }

    /**
     * POST /db-schema-viewer/layouts/{slug}/default
     * Mark a layout as the default one.
     */
    public function setDefault(string $slug): JsonResponse
    {
        $path = $this->path($slug);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Layout not found'], 404);
        }

        $this->clearDefault();

        $layout = $this->readFile($path);
        $layout['is_default'] = true;
        $this->writeFile($slug, $layout);

        return response()->json($layout);
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function path(string $slug): string
    {
        // Prevent path traversal
        $slug = basename($slug);

        return "{$this->dir}/{$slug}.json";
    }

    private function readFile(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    private function writeFile(string $slug, array $payload): void
    {
        file_put_contents(
            $this->path($slug),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function buildPayload(string $slug, array $data): array
    {
        return [
            'slug'           => $slug,
            'name'           => $data['name'],
            'positions'      => $data['positions'] ?? [],
            'comments'       => $data['comments'] ?? [],
            'column_comments'=> $data['column_comments'] ?? [],
            'is_default'     => $data['is_default'] ?? false,
            'saved_at'       => now()->toIso8601String(),
        ];
    }

    /**
     * Generate a slug that doesn't collide with existing files.
     */
    private function uniqueSlug(string $name, string $exclude = ''): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (file_exists($this->path($slug)) && $slug !== $exclude) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Remove is_default flag from all layout files.
     */
    private function clearDefault(): void
    {
        foreach (glob("{$this->dir}/*.json") ?: [] as $path) {
            $data = $this->readFile($path);
            if ($data && ! empty($data['is_default'])) {
                $data['is_default'] = false;
                $this->writeFile($data['slug'], $data);
            }
        }
    }
}
