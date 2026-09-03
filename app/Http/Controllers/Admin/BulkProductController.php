<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Services\BulkProductImporter; use Illuminate\Http\Request;
class BulkProductController extends Controller { public function index(){return view('admin.bulk-upload');} public function store(Request $r,BulkProductImporter $importer){$d=$r->validate(['file'=>'required|file|mimes:csv,xlsx,xls|max:20480'],['file.uploaded'=>'The file could not be uploaded. Keep it under 20 MB and use CSV, XLS, or XLSX.']);$result=$importer->import($d['file']->getRealPath());return back()->with('result',$result);} }
