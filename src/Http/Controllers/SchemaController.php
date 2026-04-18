<?php

namespace Sainover\DbSchemaViewer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sainover\DbSchemaViewer\Services\SchemaExtractor;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaExtractor $extractor,
        private readonly LayoutController $layouts,
    ) {}

    public function index(Request $request)
    {
        return view('db-schema-viewer::schema', [
            'tables'  => $this->extractor->getTables(),
            'layouts' => $this->layouts->index()->getData(true),
        ]);
    }
}
