<?php

namespace App\Modules\Invoicing\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SchemaController extends Controller
{
    public function index()
    {
        return inertia('Invoicing/SII/Schemas');
    }
    
    public function download(Request $request)
    {
        // TODO: Implement schema download
    }
    
    public function validate(Request $request)
    {
        // TODO: Implement schema validation
    }
    
    public function update(Request $request)
    {
        // TODO: Implement schema update
    }
    
    public function destroy($schema)
    {
        // TODO: Implement schema deletion
    }
}