<?php

namespace Sainover\DbSchemaViewer\Http\Controllers;

use Illuminate\Routing\Controller;
use Sainover\DbSchemaViewer\Services\SchemaExtractor;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaExtractor $extractor,
    ) {}

    public function index()
    {
        return view('db-schema-viewer::schema', [
            'tables'  => $this->extractor->getTables(),
        ]);
    }
}
