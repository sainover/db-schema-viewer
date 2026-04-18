<?php

namespace Sainover\ERDiagram\Http\Controllers;

use Illuminate\Routing\Controller;
use Sainover\ERDiagram\Services\SchemaExtractor;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaExtractor $extractor,
    ) {}

    public function index()
    {
        return view('erdiagram::schema', [
            'tables'  => $this->extractor->getTables(),
        ]);
    }
}
