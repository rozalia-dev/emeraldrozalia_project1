<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Services\BulkProductImporter; use Illuminate\Http\Request;
class BulkProductController extends Controller { public function index(){return view('admin.bulk-upload');} public function store(Request $r,BulkProductImporter $importer){$d=$r->validate(['file'=>'required|file|mimes:csv,xlsx,xls|max:20480']);$result=$importer->import($d['file']->getRealPath());return back()->with('result',$result);} }
