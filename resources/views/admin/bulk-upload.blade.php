@extends('layouts.admin')

@section('title', 'Bulk Product Upload')

@php
    $importResult = session('result');
    $hasImportResult = is_array($importResult);
    $importErrors = $hasImportResult ? ($importResult['errors'] ?? []) : [];
    $bulkFile = session('bulk_file');
    $uploadId = session('bulk_upload_id');
    $activeStep = $hasImportResult ? 5 : ($bulkFile ? 2 : 1);
    $totalRows = $hasImportResult ? (int) ($importResult['total'] ?? 0) : 0;
    $validRows = $hasImportResult ? (int) ($importResult['imported'] ?? 0) : 0;
    $errorRows = count($importErrors);

    $mappingRows = [
        ['source' => 'Product Name', 'preview' => 'Emerald Signature Cap', 'target' => 'Product Name', 'type' => 'Text', 'required' => true, 'mapped' => true],
        ['source' => 'SKU', 'preview' => 'ERCAP-GRN-001', 'target' => 'SKU / Barcode', 'type' => 'Text', 'required' => true, 'mapped' => true],
        ['source' => 'Category', 'preview' => 'Caps', 'target' => 'Category', 'type' => 'Dropdown', 'required' => true, 'mapped' => true],
        ['source' => 'Collection', 'preview' => "Men's Collection", 'target' => 'Collection', 'type' => 'Dropdown', 'required' => false, 'mapped' => true],
        ['source' => 'Price (EUR)', 'preview' => '29.90', 'target' => 'Selling Price', 'type' => 'Number', 'required' => true, 'mapped' => true],
        ['source' => 'Compare Price', 'preview' => '39.90', 'target' => 'Compare At Price', 'type' => 'Number', 'required' => false, 'mapped' => true],
        ['source' => 'Stock', 'preview' => '245', 'target' => 'Stock Quantity', 'type' => 'Number', 'required' => true, 'mapped' => true],
        ['source' => 'Description', 'preview' => 'Premium quality cap...', 'target' => 'Short Description', 'type' => 'Text', 'required' => true, 'mapped' => true],
        ['source' => 'Images', 'preview' => 'cap1.jpg|cap2.jpg|cap3.jpg', 'target' => 'Images', 'type' => 'Text (URLs)', 'required' => false, 'mapped' => true],
        ['source' => 'Status', 'preview' => 'Published', 'target' => 'Product Status', 'type' => 'Dropdown', 'required' => false, 'mapped' => true],
        ['source' => 'Weight (kg)', 'preview' => '0.25', 'target' => 'Weight', 'type' => 'Number', 'required' => false, 'mapped' => true],
        ['source' => 'Tags', 'preview' => 'green, premium, signature', 'target' => 'Tags', 'type' => 'Text', 'required' => false, 'mapped' => true],
        ['source' => 'GTIN', 'preview' => '8901122334457', 'target' => 'GTIN / Barcode', 'type' => 'Text', 'required' => false, 'mapped' => true],
        ['source' => 'Custom Field 1', 'preview' => 'New Arrival', 'target' => 'Unmapped', 'type' => 'Text', 'required' => false, 'mapped' => false],
        ['source' => 'Custom Field 2', 'preview' => 'Summer 2025', 'target' => 'Unmapped', 'type' => 'Text', 'required' => false, 'mapped' => false],
    ];
    $mappingOptions = ['Product Name', 'SKU / Barcode', 'Category', 'Collection', 'Selling Price', 'Compare At Price', 'Stock Quantity', 'Short Description', 'Images', 'Product Status', 'Weight', 'Tags', 'GTIN / Barcode', 'Unmapped'];
    $previewRows = [
        ['name' => 'Emerald Signature Cap', 'sku' => 'ERCAP-GRN-001', 'price' => '29.90', 'stock' => '245'],
        ['name' => 'Premium Black Cap', 'sku' => 'ERCAP-BLK-002', 'price' => '34.90', 'stock' => '89'],
        ['name' => 'Emerald Leather Bracelet', 'sku' => 'ERBR-LBR-003', 'price' => '49.90', 'stock' => '156'],
        ['name' => 'Emerald Chronograph Watch', 'sku' => 'ERWATCH-SB-004', 'price' => '199.00', 'stock' => '0'],
        ['name' => 'Aviator Sunglasses', 'sku' => 'ERSG-AVI-005', 'price' => '69.90', 'stock' => '312'],
    ];
    $categoryMapTotal = 10;
    $categoryMapMapped = 8;
@endphp

@section('content')
    <div class="bu-page" data-bulk-upload>
        <div class="bu-page-head">
            <div>
                <p class="bu-eyebrow">WEBSITE &amp; PRODUCTS / DATA OPERATIONS</p>
                <h1>Bulk Product Upload</h1>
                <p class="bu-subtitle">Upload multiple products using CSV or Excel files. Our intelligent mapper will help you map your columns to system fields.</p>
            </div>
            <div class="bu-head-meta">
                <nav class="bu-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('admin.dashboard') }}">Home</a>
                    <x-icon name="chevron-right" size="12" />
                    <a href="{{ route('admin.resource', 'products') }}">Website &amp; Products</a>
                    <x-icon name="chevron-right" size="12" />
                    <strong>Bulk Product Upload</strong>
                </nav>
                <div class="bu-date-card">
                    <x-icon name="clock" size="22" />
                    <span><small>Today</small><strong>{{ now()->format('l, j F Y') }}</strong><b>{{ now()->format('H:i') }}</b></span>
                </div>
            </div>
        </div>

        <ol class="bu-stepper" data-bu-stepper>
            @foreach ([1 => ['Upload File', 'Select & validate file'], 2 => ['Map Columns', 'Map to system fields'], 3 => ['Validate Data', 'Check for errors'], 4 => ['Review & Confirm', 'Preview & confirm'], 5 => ['Import Products', 'Import & complete']] as $number => $step)
                @php $stepState = $number < $activeStep ? 'is-complete' : ($number === $activeStep ? 'is-active' : ''); @endphp
                <li class="{{ $stepState }}" data-bu-step="{{ $number }}">
                    <span class="bu-step-circle">@if ($number < $activeStep)<x-icon name="check" size="15" />@else{{ $number }}@endif</span>
                    <span><strong>{{ $step[0] }}</strong><small>{{ $step[1] }}</small></span>
                </li>
            @endforeach
        </ol>

        @if ($hasImportResult)
            <div class="bu-result-banner" role="status">
                <x-icon name="check" size="18" />
                <span><strong>{{ $validRows }} product{{ $validRows === 1 ? '' : 's' }} imported successfully.</strong> {{ $errorRows }} row{{ $errorRows === 1 ? '' : 's' }} need{{ $errorRows === 1 ? 's' : '' }} attention.</span>
                @if ($errorRows)
                    <details><summary>View errors</summary><ul>@foreach ($importErrors as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul></details>
                @endif
            </div>
        @endif

        <form class="bu-form" method="post" enctype="multipart/form-data" action="{{ route('admin.bulk-upload.store') }}" data-bu-form>
            @csrf
            <div class="bu-workspace">
                <div class="bu-primary-column">
                    <section class="bu-panel bu-upload-panel">
                        <div class="bu-panel-heading">
                            <div><h2>1. Upload Your File</h2><p>Supported formats: CSV, XLSX</p></div>
                            <x-icon name="upload" size="18" />
                        </div>
                        <label class="bu-dropzone" for="bulk-file" data-bu-dropzone>
                            <x-icon name="upload" size="30" />
                            <strong>Drag &amp; drop your file here</strong>
                            <span>or</span>
                            <span class="bu-choose-button">Choose File</span>
                            <input id="bulk-file" type="file" name="file" accept=".csv,.xlsx,.xls" required data-bu-file-input>
                        </label>
                        <div class="bu-file-row" data-bu-file-row>
                            <x-icon name="file-text" size="22" />
                            <span><strong data-bu-file-name>{{ $bulkFile ?: 'No file selected yet' }}</strong><small data-bu-file-meta>{{ $bulkFile ? 'Upload received and ready for mapping' : 'Choose a CSV or XLSX file to begin' }}</small></span>
                            <b class="bu-file-state {{ $bulkFile ? 'is-success' : '' }}" data-bu-file-state>{{ $bulkFile ? 'File uploaded successfully' : 'Waiting for file' }}</b>
                        </div>
                        <p class="bu-upload-note"><x-icon name="help" size="13" /> Maximum file size: 25 MB. Use the sample template for the recommended column headings.</p>
                    </section>

                    <section class="bu-panel bu-mapping-panel">
                        <div class="bu-panel-heading bu-panel-heading-wide">
                            <div><h2>2. Map Columns to System Fields</h2><p>Map your file columns to the Emerald Rozalia product fields.</p></div>
                            <div class="bu-tool-actions">
                                <button type="button" class="bu-tool-button" data-bu-auto-map><x-icon name="refresh" size="13" /> Auto Map</button>
                                <button type="button" class="bu-tool-button" data-bu-reset-map><x-icon name="refresh" size="13" /> Reset Mapping</button>
                                <button type="button" class="bu-tool-button" data-bu-show-unmapped><x-icon name="filter" size="13" /> Show Unmapped <b>(2)</b></button>
                            </div>
                        </div>
                        <div class="bu-table-scroll">
                            <table class="bu-mapping-table">
                                <thead><tr><th>File Column (Your File)</th><th>Preview Data</th><th></th><th>System Field (Map To)</th><th>Field Type</th><th>Required</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach ($mappingRows as $index => $row)
                                        <tr data-bu-map-row data-mapped="{{ $row['mapped'] ? 'true' : 'false' }}">
                                            <td><strong>{{ $row['source'] }}</strong></td>
                                            <td><span title="{{ $row['preview'] }}">{{ $row['preview'] }}</span></td>
                                            <td class="bu-map-arrow"><x-icon name="arrow-right" size="13" /></td>
                                            <td>
                                                <select name="mapping[{{ $index }}]" data-bu-map-select data-default-target="{{ $row['target'] }}">
                                                    @foreach ($mappingOptions as $option)<option value="{{ $option }}" @selected($row['target'] === $option)>{{ $option }}</option>@endforeach
                                                </select>
                                            </td>
                                            <td>{{ $row['type'] }}</td>
                                            <td><span class="bu-required {{ $row['required'] ? 'yes' : 'no' }}">{{ $row['required'] ? 'Yes' : 'No' }}</span></td>
                                            <td><span class="bu-map-status {{ $row['mapped'] ? 'is-mapped' : 'is-unmapped' }}" data-bu-map-status><x-icon name="{{ $row['mapped'] ? 'check' : 'refresh' }}" size="11" /> {{ $row['mapped'] ? 'Mapped' : 'Unmapped' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="bu-secondary-column">
                    <section class="bu-panel bu-preview-panel">
                        <div class="bu-panel-heading"><div><h2>3. Data Preview</h2><p>First 5 rows from your file</p></div><x-icon name="eye" size="18" /></div>
                        <div class="bu-table-scroll">
                            <table class="bu-preview-table"><thead><tr><th>Product Name</th><th>SKU</th><th>Price (EUR)</th><th>Stock</th></tr></thead><tbody>@foreach ($previewRows as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['sku'] }}</td><td>{{ $row['price'] }}</td><td class="{{ $row['stock'] === '0' ? 'is-zero' : '' }}">{{ $row['stock'] }}</td></tr>@endforeach</tbody></table>
                        </div>
                        <p class="bu-preview-note"><x-icon name="help" size="12" /> Preview uses sample rows until a file is selected.</p>
                    </section>

                    <section class="bu-panel bu-settings-panel">
                        <div class="bu-panel-heading"><div><h2>4. Import Settings</h2><p>Choose how the uploaded products should be handled.</p></div><x-icon name="settings" size="18" /></div>
                        <div class="bu-setting-list">
                            <label class="bu-setting-toggle"><span>Update existing products <small>(match by SKU)</small></span><input type="hidden" name="update_existing" value="0"><input type="checkbox" name="update_existing" value="1" checked><i aria-hidden="true"><b></b></i></label>
                            <label class="bu-setting-toggle"><span>Skip products with errors</span><input type="hidden" name="skip_errors" value="0"><input type="checkbox" name="skip_errors" value="1" checked><i aria-hidden="true"><b></b></i></label>
                        </div>
                        <div class="bu-setting-fields">
                            <label>Default Product Status<select name="default_status"><option>Published</option><option>Draft</option></select></label>
                            <label>Default Visibility<select name="default_visibility"><option>Visible</option><option>Hidden</option></select></label>
                            <label>Assign to Collection (Optional)<select name="collection" disabled><option>Select collection</option></select></label>
                        </div>
                        <div class="bu-order-categories"><strong>Assign to Six Order Master Categories</strong><small>All selected categories will be available for uploaded products.</small><div>@foreach (['online' => 'Online Orders', 'corporate' => 'Corporate Orders', 'bulk' => 'Bulk Orders', 'franchise' => 'Franchise Orders', 'franchise_retail' => 'Franchise Retail Orders', 'buyer' => 'Buyer Orders'] as $key => $label)<label><input type="checkbox" name="order_categories[]" value="{{ $key }}" checked><span><x-icon name="check" size="11" />{{ $label }}</span></label>@endforeach</div></div>
                    </section>
                </div>

                <aside class="bu-summary-column">
                    <section class="bu-summary-card">
                        <div class="bu-summary-heading"><h2>Upload Summary</h2><x-icon name="file-text" size="17" /></div>
                        <dl class="bu-stat-list">
                            <div><dt>Total Rows</dt><dd>{{ $hasImportResult ? number_format($totalRows) : '—' }}</dd></div>
                            <div><dt>Valid Rows</dt><dd>{{ $hasImportResult ? number_format($validRows) : '—' }}</dd></div>
                            <div><dt>Rows with Errors</dt><dd class="is-error">{{ $hasImportResult ? number_format($errorRows) : '—' }}</dd></div>
                            <div><dt>Empty Rows</dt><dd>{{ $hasImportResult ? '0' : '—' }}</dd></div>
                        </dl>
                        <dl class="bu-file-summary">
                            <div><dt>File Name</dt><dd data-bu-summary-file>{{ $bulkFile ?: 'Awaiting file upload' }}</dd></div>
                            <div><dt>File Size</dt><dd data-bu-summary-size>—</dd></div>
                            <div><dt>Uploaded By</dt><dd>{{ auth()->user()->name ?? 'Admin User' }}</dd></div>
                        </dl>
                    </section>

                    <section class="bu-summary-card">
                        <div class="bu-summary-heading"><h2>Category Mapping Summary</h2><button type="button" class="bu-reset-button" data-bu-reset-map>Reset</button></div>
                        <div class="bu-donut" aria-label="{{ $categoryMapMapped }} of {{ $categoryMapTotal }} categories mapped"><span>{{ $categoryMapTotal }}<small>Total</small></span></div>
                        <div class="bu-legend"><span><i class="is-mapped"></i> Mapped <b>{{ $categoryMapMapped }} (80%)</b></span><span><i class="is-unmapped"></i> Unmapped <b>{{ $categoryMapTotal - $categoryMapMapped }} (20%)</b></span></div>
                    </section>

                    <section class="bu-summary-card bu-quick-actions">
                        <div class="bu-summary-heading"><h2>Quick Actions</h2><x-icon name="arrow-right" size="15" /></div>
                        <button type="button" data-bu-download-sample><x-icon name="download" size="14" /> Download Sample File</button>
                        <button type="button"><x-icon name="clock" size="14" /> View Upload History</button>
                        <button type="button" data-bu-save-mapping><x-icon name="download" size="14" /> Save Mapping Template</button>
                        <button type="button"><x-icon name="help" size="14" /> Bulk Upload Guidelines</button>
                        <button type="button"><x-icon name="check" size="14" /> Check Data Quality Rules</button>
                    </section>

                    <section class="bu-trace-card">
                        <div class="bu-summary-heading"><h2>UUID Traceability</h2><x-icon name="tag" size="17" /></div>
                        <p>Every bulk upload is assigned a unique UUID for traceability.</p>
                        <dl><div><dt>Upload ID (UUID)</dt><dd data-bu-upload-id>{{ $uploadId ?: 'Assigned on upload' }}</dd></div><div><dt>Status</dt><dd data-bu-summary-status>{{ $bulkFile ? 'File uploaded' : 'Waiting for file' }}</dd></div><div><dt>Initiated By</dt><dd>{{ auth()->user()->name ?? 'Admin User' }}</dd></div></dl>
                    </section>
                </aside>
            </div>

            <div class="bu-bottom-actions">
                <div class="bu-bottom-left"><a class="bu-button bu-button-muted" href="{{ route('admin.dashboard') }}">Cancel</a><button class="bu-button bu-button-muted" type="button" data-bu-save-mapping>Save Mapping Template</button></div>
                <div class="bu-bottom-right"><button class="bu-button bu-button-muted" type="button" data-bu-back>Back</button><button class="bu-button bu-button-primary" type="submit"><x-icon name="check" size="14" /> VALIDATE &amp; IMPORT <x-icon name="arrow-right" size="15" /></button></div>
            </div>
        </form>
    </div>
@endsection
