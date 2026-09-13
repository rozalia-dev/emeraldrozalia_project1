@extends('layouts.admin')
@section('title', 'Reviews & Ratings')
@section('content')
@php
    $reviewUrl = fn (array $query = []) => route('admin.resource', array_merge(['module' => 'reviews-ratings'], $query));
    $reviewSetting = fn (string $key, mixed $default = false): mixed => $reviewSettings[$key] ?? $default;
    $reviewBool = fn (string $key, bool $default = false): bool => filter_var($reviewSetting($key, $default), FILTER_VALIDATE_BOOLEAN);
@endphp
<style>
/* Base variables for colors to match mockup exactly */
:root {
    --text-main: #1f2937;
    --text-muted: #6b7280;
    --bg-main: #f9fafb;
    --bg-card: #ffffff;
    --border-color: #e5e7eb;
    --primary-green: #059669; /* Emerald */
    --star-gold: #f59e0b;
    --badge-bg-green: #d1fae5;
    --badge-text-green: #065f46;
    --badge-bg-red: #fee2e2;
    --badge-text-red: #991b1b;
    --badge-bg-orange: #ffedd5;
    --badge-text-orange: #9a3412;
    --btn-border: #d1d5db;
    --hover-bg: #f3f4f6;
}

.rr-dashboard {
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--text-main);
    padding: 24px;
    background: #fff; /* Whole area seems white in mockup */
}

/* Header */
.rr-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.rr-header-left h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 4px 0;
}
.rr-header-left p {
    color: var(--text-muted);
    font-size: 14px;
    margin: 0;
}
.rr-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.rr-date-widget {
    display: flex;
    align-items: center;
    gap: 12px;
}
.rr-date-icon {
    width: 40px;
    height: 40px;
    background: #f3f4f6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}
.rr-date-info span {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
}
.rr-date-info strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
}
.rr-time {
    font-size: 18px;
    font-weight: 700;
}

/* KPI Cards */
.rr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.rr-kpi-card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.rr-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.rr-kpi-icon svg { width: 24px; height: 24px; }
.rr-kpi-icon.bg-green { background: #059669; }
.rr-kpi-icon.bg-purple { background: #8b5cf6; }
.rr-kpi-icon.bg-orange { background: #f97316; }
.rr-kpi-icon.bg-blue { background: #3b82f6; }
.rr-kpi-icon.bg-emerald { background: #10b981; }

.rr-kpi-content h3 {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0 0 4px 0;
    font-weight: 500;
}
.rr-kpi-content .rr-kpi-val {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
}
.rr-kpi-trend {
    font-size: 12px;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.rr-kpi-trend.up { color: #059669; }
.rr-kpi-trend.down { color: #dc2626; }
.rr-kpi-trend span { color: var(--text-muted); }

/* Layout Columns */
.rr-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 32px;
}
.rr-main-col {
    min-width: 0; /* Important for preventing grid blowout and allowing table overflow */
}
@media (max-width: 1200px) {
    .rr-layout {
        grid-template-columns: 1fr;
    }
    .rr-sidebar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
    }
}

/* Tabs */
.rr-tabs {
    display: flex;
    gap: 24px;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 24px;
}
.rr-tab {
    padding: 12px 0;
    color: var(--text-muted);
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.rr-tab.active {
    color: var(--primary-green);
    border-bottom-color: var(--primary-green);
}
.rr-tab:hover:not(.active) {
    color: var(--text-main);
}

/* Toolbar */
.rr-toolbar {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    align-items: center;
}
.rr-search {
    position: relative;
    flex: 1;
    max-width: 300px;
}
.rr-search input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--btn-border);
    border-radius: 6px;
    font-size: 14px;
}
.rr-search svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    width: 16px;
    height: 16px;
}
.rr-filter-btn, .rr-select, .rr-reset-btn {
    padding: 8px 12px;
    border: 1px solid var(--btn-border);
    border-radius: 6px;
    background: #fff;
    font-size: 14px;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.rr-select { padding-right: 32px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>'); background-repeat: no-repeat; background-position: right 12px center; }
.rr-reset-btn { border: none; color: var(--text-muted); }

/* Table */
.rr-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 16px;
}
.rr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 1000px; /* Force table to not squish beyond this, triggering horizontal scroll */
}
.rr-table th, .rr-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.rr-table th {
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    font-size: 11px;
    background: #f9fafb;
    white-space: nowrap;
}
.rr-table tbody tr:hover { background: var(--hover-bg); }

/* Table Cells specific */
.rr-td-review { min-width: 250px; }
.rr-td-review h4 { margin: 0 0 4px 0; font-size: 13px; font-weight: 600; }
.rr-td-review p { margin: 0; color: var(--text-muted); max-width: 300px; line-height: 1.4; }
.rr-td-product { display: flex; align-items: center; gap: 12px; min-width: 180px; }
.rr-product-img { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; background:#eee; flex-shrink: 0; }
.rr-td-product div strong { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.rr-td-product div span { color: var(--text-muted); font-size: 11px; white-space: nowrap; }

.rr-customer, .rr-date { white-space: nowrap; }
.rr-stars { color: var(--border-color); display:flex; gap:2px; font-size:14px; margin-bottom:4px; white-space: nowrap; }
.rr-stars .filled { color: var(--star-gold); }
.rr-rating-val { font-weight: 600; }

.rr-customer strong { display: block; font-weight: 600; }
.rr-verified { color: #059669; font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top:2px; }
.rr-source { display: flex; align-items: center; gap: 6px; color: var(--text-muted); }
.rr-badge { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; display: inline-block; }
.rr-badge.approved { background: var(--badge-bg-green); color: var(--badge-text-green); }
.rr-badge.pending { background: var(--badge-bg-orange); color: var(--badge-text-orange); }
.rr-badge.flagged { background: var(--badge-bg-red); color: var(--badge-text-red); }
.rr-date { color: var(--text-muted); }
.rr-date strong { display: block; color: var(--text-main); font-weight:500;}

.rr-actions { display: flex; gap: 8px; }
.rr-action-form { display: flex; margin: 0; }
.rr-btn-icon { width: 28px; height: 28px; border: 1px solid var(--btn-border); border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #fff; cursor: pointer; color: var(--text-muted); }
.rr-btn-icon:hover { background: var(--hover-bg); }
.rr-btn-icon svg { width: 14px; height: 14px; }

/* Pagination */
.rr-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 32px;
}
.rr-page-controls { display: flex; gap: 4px; align-items: center; }
.rr-page-btn { padding: 6px 12px; border: 1px solid var(--btn-border); border-radius: 4px; background: #fff; cursor: pointer; }
.rr-page-btn.active { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }

/* Bottom Widgets Grid */
.rr-bottom-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 24px;
}
.rr-widget h3 { font-size: 14px; font-weight: 600; margin: 0 0 16px 0; }
.rr-analytics-stats { display: flex; gap: 16px; margin-bottom: 16px; }
.rr-analytics-stats > div { flex: 1; }
.rr-analytics-stats span { display: block; font-size: 11px; color: var(--text-muted); }
.rr-analytics-stats strong { display: block; font-size: 18px; font-weight: 700; margin: 4px 0; }
.rr-analytics-stats small { font-size: 11px; color: #059669; }

/* Settings Toggles */
.rr-toggle-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 12px; }
.rr-toggle { width: 32px; height: 18px; background: #d1d5db; border-radius: 9px; position: relative; cursor: pointer; }
.rr-toggle.active { background: var(--primary-green); }
.rr-toggle::after { content: ''; width: 14px; height: 14px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: 0.2s; }
.rr-toggle.active::after { transform: translateX(14px); }
.rr-manage-btn { width: 100%; padding: 8px; border: 1px solid var(--btn-border); border-radius: 6px; background: #fff; color: var(--text-main); font-weight: 500; cursor: pointer; margin-top: 8px;}

/* Guidelines */
.rr-guideline { display: flex; gap: 8px; font-size: 12px; margin-bottom: 12px; align-items: flex-start; }
.rr-guideline svg { color: var(--primary-green); width: 16px; height: 16px; flex-shrink: 0; }

/* Top Products */
.rr-top-product { display: flex; gap: 12px; margin-bottom: 12px; align-items: center; }
.rr-top-product img { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; }
.rr-top-product h4 { margin: 0; font-size: 12px; font-weight: 600; }
.rr-top-product-rating { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--text-muted); }
.rr-top-product-rating svg { width: 12px; height: 12px; color: var(--star-gold); }

/* Right Sidebar */
.rr-sidebar > div {
    margin-bottom: 32px;
}
.rr-sidebar h3 { font-size: 12px; font-weight: 700; color: var(--primary-green); text-transform: uppercase; margin: 0 0 16px 0; }

/* Ratings Breakdown */
.rr-breakdown-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 8px; }
.rr-breakdown-label { width: 45px; }
.rr-breakdown-bar { flex: 1; height: 8px; background: var(--hover-bg); border-radius: 4px; overflow: hidden; }
.rr-breakdown-fill { height: 100%; border-radius: 4px; }
.rr-breakdown-val { width: 80px; text-align: right; color: var(--text-muted); }

/* Review Sources */
.rr-sources-list { display: flex; flex-direction: column; gap: 8px; font-size: 12px; }
.rr-source-item { display: flex; align-items: center; justify-content: space-between; }
.rr-source-label { display: flex; align-items: center; gap: 6px; }
.rr-source-dot { width: 8px; height: 8px; border-radius: 50%; }

/* Quick Actions */
.rr-quick-actions { display: flex; flex-direction: column; gap: 12px; }
.rr-action-link { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-main); text-decoration: none; }
.rr-action-link svg { color: var(--text-muted); width: 16px; height: 16px; }

/* UUID Traceability */
.rr-uuid-text { font-size: 12px; color: var(--text-muted); margin: 0 0 12px 0; line-height: 1.4; }
.rr-uuid-code { font-size: 12px; font-family: monospace; word-break: break-all; margin-bottom: 12px; }
.rr-kpi-link { color: inherit; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease; }
.rr-kpi-link:hover, .rr-kpi-link:focus-visible { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5, 150, 105, .12); outline: none; }
.rr-tabs { overflow-x: auto; scrollbar-width: thin; }
.rr-tab { flex: 0 0 auto; text-decoration: none; }
.rr-bulk-toolbar { display: flex; align-items: end; gap: 12px; margin: 0 0 14px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: #f8fafc; }
.rr-bulk-toolbar label { display: grid; gap: 5px; font-size: 11px; font-weight: 600; color: var(--text-muted); }
.rr-bulk-toolbar select { min-width: 190px; padding: 8px 10px; border: 1px solid var(--btn-border); border-radius: 6px; background: #fff; color: var(--text-main); }
.rr-bulk-count { font-size: 12px; color: var(--text-muted); padding-bottom: 9px; }
.rr-import-card { display: grid; gap: 14px; margin-bottom: 18px; padding: 18px; border: 1px solid #bbf7d0; border-radius: 10px; background: #f0fdf4; }
.rr-import-card h2 { margin: 0 0 5px; font-size: 16px; }
.rr-import-card p { margin: 0; color: var(--text-muted); font-size: 12px; line-height: 1.5; }
.rr-import-card form { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; }
.rr-import-card label { display: grid; gap: 5px; font-size: 12px; font-weight: 600; }
.rr-import-card input[type=file] { min-width: 260px; padding: 8px; border: 1px solid var(--btn-border); border-radius: 6px; background: #fff; }
.rr-form-error { color: #991b1b !important; font-weight: 600; }
.rr-per-page-form { margin: 0; }
.rr-per-page-form .rr-select { border: 1px solid var(--btn-border); }
.rr-per-page-form select { border: 0; background: transparent; color: inherit; font-size: inherit; }
.rr-settings-form { display: grid; gap: 11px; }
.rr-toggle-row { gap: 10px; }
.rr-toggle-row > span:first-child { flex: 1; }
.rr-toggle { display: inline-flex; align-items: center; justify-content: flex-end; width: 38px; height: 22px; padding: 2px; border: 0; }
.rr-toggle input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.rr-toggle::after { width: 16px; height: 16px; }
.rr-toggle.active::after { transform: translateX(16px); }
.rr-settings-form .rr-select { padding: 4px 24px 4px 8px; font-size: 11px; }
.rr-settings-link { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 10px; font-size: 11px; color: var(--primary-green); text-decoration: none; }
.rr-analytics-chart { display: flex; align-items: end; gap: 2px; height: 70px; padding: 8px 0 0; border-bottom: 1px solid var(--border-color); }
.rr-chart-bar { flex: 1; min-width: 2px; border-radius: 2px 2px 0 0; background: linear-gradient(180deg, #34d399, #059669); transition: height .25s ease; }
.rr-chart-caption { display: flex; justify-content: space-between; gap: 12px; margin-top: 8px; font-size: 10px; color: var(--text-muted); }
.rr-top-product h4 a { color: inherit; text-decoration: none; }
.rr-top-product h4 a:hover { color: var(--primary-green); text-decoration: underline; }
.rr-dialog { width: min(520px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 12px; box-shadow: 0 24px 80px rgba(15, 23, 42, .28); }
.rr-dialog::backdrop { background: rgba(15, 23, 42, .45); }
.rr-dialog-card { padding: 22px; background: #fff; color: var(--text-main); }
.rr-dialog-card header { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 18px; }
.rr-dialog-card header h2 { margin: 3px 0 0; font-size: 20px; }
.rr-dialog-eyebrow { font-size: 10px; color: var(--primary-green); text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.rr-dialog-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 0 0 18px; }
.rr-dialog-details div { padding: 10px; border: 1px solid var(--border-color); border-radius: 7px; }
.rr-dialog-details dt { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
.rr-dialog-details dd { margin: 4px 0 0; font-size: 12px; font-weight: 600; overflow-wrap: anywhere; }
.rr-dialog-comment { padding: 12px; border-radius: 8px; background: #f8fafc; }
.rr-dialog-comment h3 { margin: 0 0 5px; font-size: 12px; }
.rr-dialog-comment p { margin: 0; font-size: 13px; line-height: 1.55; white-space: pre-wrap; }
.rr-dialog-list { display: grid; gap: 10px; margin: 0 0 18px; padding-left: 20px; color: var(--text-main); font-size: 13px; line-height: 1.55; }
@media (max-width: 700px) {
    .rr-dashboard { padding: 16px; }
    .rr-header { flex-direction: column; gap: 14px; }
    .rr-bulk-toolbar, .rr-import-card form { align-items: stretch; flex-direction: column; }
    .rr-bulk-toolbar select, .rr-import-card input[type=file], .rr-import-card button { width: 100%; }
    .rr-bulk-count { padding-bottom: 0; }
    .rr-dialog-details { grid-template-columns: 1fr; }
}
</style>

<div class="rr-dashboard" data-review-source="review-records">
    <!-- Header -->
    <div class="rr-header">
        <div class="rr-header-left">
            <h1>Reviews & Ratings</h1>
            <p>Manage customer reviews, ratings and Q&A to build trust and improve product visibility.</p>
        </div>
        <div class="rr-header-right">
            <div class="rr-date-widget">
                <div class="rr-date-icon">
                    <x-icon name="calendar" size="20" />
                </div>
                <div class="rr-date-info">
                    <span>Today</span>
                    <strong>{{ now(config('app.timezone'))->format('l, j F Y') }}</strong>
                </div>
            </div>
            <div class="rr-time">{{ now(config('app.timezone'))->format('g:i A') }}</div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rr-kpi-grid" aria-label="Review metrics">
        <a class="rr-kpi-card rr-kpi-link" href="{{ $reviewUrl(['rating' => 5]) }}" data-rr-metric aria-label="Filter reviews by five star rating">
            <div class="rr-kpi-icon bg-green"><x-icon name="star" /></div>
            <div class="rr-kpi-content">
                <h3>Average Rating</h3>
                <p class="rr-kpi-val">{{ number_format($stats['average_rating'], 1) }} <span style="font-size:14px;color:var(--text-muted);font-weight:400;">/ 5</span></p>
                <div class="rr-kpi-trend"><span>Live review data</span></div>
            </div>
        </a>
        <a class="rr-kpi-card rr-kpi-link" href="{{ $reviewUrl() }}" data-rr-metric aria-label="View all reviews">
            <div class="rr-kpi-icon bg-purple"><x-icon name="message-square" /></div>
            <div class="rr-kpi-content">
                <h3>Total Reviews</h3>
                <p class="rr-kpi-val">{{ number_format($stats['total']) }}</p>
                <div class="rr-kpi-trend"><span>All statuses</span></div>
            </div>
        </a>
        <a class="rr-kpi-card rr-kpi-link" href="{{ $reviewUrl(['tab' => 'pending']) }}" data-rr-metric aria-label="View pending reviews">
            <div class="rr-kpi-icon bg-orange"><x-icon name="archive" /></div>
            <div class="rr-kpi-content">
                <h3>Pending Reviews</h3>
                <p class="rr-kpi-val">{{ number_format($stats['pending']) }}</p>
                <div class="rr-kpi-trend"><span>Awaiting moderation</span></div>
            </div>
        </a>
        <a class="rr-kpi-card rr-kpi-link" href="{{ $reviewUrl(['tab' => 'approved']) }}" data-rr-metric aria-label="View approved reviews">
            <div class="rr-kpi-icon bg-blue"><x-icon name="check-circle" /></div>
            <div class="rr-kpi-content">
                <h3>Approved Reviews</h3>
                <p class="rr-kpi-val">{{ number_format($stats['approved']) }}</p>
                <div class="rr-kpi-trend"><span>Published reviews</span></div>
            </div>
        </a>
        <a class="rr-kpi-card rr-kpi-link" href="{{ $reviewUrl(['tab' => 'flagged']) }}" data-rr-metric aria-label="View flagged reviews">
            <div class="rr-kpi-icon bg-emerald"><x-icon name="shield" /></div>
            <div class="rr-kpi-content">
                <h3>Flagged / Reported</h3>
                <p class="rr-kpi-val">{{ number_format($stats['flagged']) }}</p>
                <div class="rr-kpi-trend"><span>Flagged or reported</span></div>
            </div>
        </a>
    </div>

    <!-- Main Layout -->
    <div class="rr-layout">
        <!-- Left Column -->
        <div class="rr-main-col">
            <!-- Tabs -->
            <nav class="rr-tabs" aria-label="Review views">
                <a class="rr-tab {{ $tab === 'all' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'all']) }}" @if($tab === 'all') aria-current="page" @endif>All Reviews</a>
                <a class="rr-tab {{ $tab === 'pending' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'pending']) }}" @if($tab === 'pending') aria-current="page" @endif>Pending Approval</a>
                <a class="rr-tab {{ $tab === 'approved' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'approved']) }}" @if($tab === 'approved') aria-current="page" @endif>Approved</a>
                <a class="rr-tab {{ $tab === 'rejected' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'rejected']) }}" @if($tab === 'rejected') aria-current="page" @endif>Disapproved / Rejected</a>
                <a class="rr-tab {{ $tab === 'flagged' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'flagged']) }}" @if($tab === 'flagged') aria-current="page" @endif>Flagged</a>
                <a class="rr-tab" href="{{ route('admin.resource', ['module' => 'inbox', 'kind' => 'questions']) }}">Customer Q&amp;A</a>
                <a class="rr-tab {{ $tab === 'import' ? 'active' : '' }}" href="{{ $reviewUrl(['tab' => 'import']) }}" @if($tab === 'import') aria-current="page" @endif>Import Reviews</a>
            </nav>

            @if($tab === 'import')
                <section class="rr-import-card" aria-labelledby="rr-import-title">
                    <div>
                        <h2 id="rr-import-title">Import review records</h2>
                        <p>Upload a CSV with <code>email</code>, <code>product_sku</code> and <code>rating</code>. Optional columns are <code>title</code> and <code>body</code>. Imported records remain Pending until moderation.</p>
                    </div>
                    <form method="post" action="{{ route('admin.reviews.import') }}" enctype="multipart/form-data">
                        @csrf
                        <label>Review CSV<input type="file" name="file" accept=".csv,text/csv,text/plain" required></label>
                        <button class="rr-filter-btn" type="submit"><x-icon name="upload" size="14" /> Import records</button>
                    </form>
                    @if($errors->has('file'))<p class="rr-form-error" role="alert">{{ $errors->first('file') }}</p>@endif
                </section>
            @endif

            <!-- Toolbar -->
            <form class="rr-toolbar" method="get" action="{{ route('admin.resource', ['module' => 'reviews-ratings']) }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="rr-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search reviews..." aria-label="Search reviews">
                </div>
                <button class="rr-filter-btn" type="submit"><x-icon name="filter" size="14" /> Apply filters</button>
                <select class="rr-select" name="rating" aria-label="Filter by rating">
                    <option value="">All Ratings</option>
                    @for($stars = 5; $stars >= 1; $stars--)
                        <option value="{{ $stars }}" @selected($rating === $stars)>{{ $stars }} Star{{ $stars === 1 ? '' : 's' }}</option>
                    @endfor
                </select>
                <select class="rr-select" name="status" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'flagged' => 'Flagged'] as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}" @selected($status === $statusValue)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
                <a class="rr-reset-btn" href="{{ route('admin.resource', ['module' => 'reviews-ratings']) }}"><x-icon name="refresh-cw" size="12" /> Reset</a>
            </form>

            <form class="rr-bulk-toolbar" id="rr-bulk-form" method="post" action="{{ route('admin.reviews.bulk-status') }}">
                @csrf
                <label>Selected review action
                    <select name="status" required>
                        <option value="">Choose an action</option>
                        <option value="approved">Approve selected</option>
                        <option value="pending">Return to pending</option>
                        <option value="flagged">Flag selected</option>
                        <option value="rejected">Reject selected</option>
                    </select>
                </label>
                <button class="rr-filter-btn" type="submit" data-rr-bulk-submit>Apply to selected</button>
                <span class="rr-bulk-count" data-rr-bulk-count>0 selected</span>
            </form>

            <!-- Table -->
            <div class="rr-table-wrap">
                <table class="rr-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" form="rr-bulk-form" data-rr-select-all aria-label="Select all visible reviews"></th>
                            <th>REVIEW</th>
                            <th>PRODUCT</th>
                            <th>RATING</th>
                            <th>CUSTOMER</th>
                            <th>SOURCE</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reviews as $review)
                        <tr>
                            <td><input type="checkbox" form="rr-bulk-form" name="ids[]" value="{{ $review->id }}" data-rr-select aria-label="Select {{ $review->title }}"></td>
                            <td class="rr-td-review">
                                <h4>{{ $review->title }}</h4>
                                <p>{{ $review->comment }}</p>
                            </td>
                            <td class="rr-td-product">
                                @if($review->image)
                                    <img class="rr-product-img" src="{{ $review->image }}" alt="{{ $review->product_name }}">
                                @else
                                    <div class="rr-product-img" role="img" aria-label="No product image"></div>
                                @endif
                                <div>
                                    <strong>{{ $review->product_name }}</strong>
                                    <span>SKU: {{ $review->sku }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="rr-stars">
                                    @for($i=1; $i<=5; $i++)
                                        <span class="{{ $i <= $review->rating ? 'filled' : '' }}">★</span>
                                    @endfor
                                </div>
                                <span class="rr-rating-val">{{ number_format($review->rating, 1) }}</span>
                            </td>
                            <td class="rr-customer">
                                <strong>{{ $review->customer }}</strong>
                                @if($review->verified)
                                <div class="rr-verified"><x-icon name="check-circle" size="12" /> Verified Buyer</div>
                                @endif
                            </td>
                            <td>
                                <div class="rr-source">
                                    <x-icon name="{{ $review->source_icon }}" size="14" /> {{ $review->source }}
                                </div>
                            </td>
                            <td>
                                <span class="rr-badge {{ $review->status_key }}">{{ $review->status }}</span>
                            </td>
                            <td class="rr-date">
                                <strong>{{ $review->date }}</strong>
                                <span>{{ $review->time }}</span>
                            </td>
                            <td>
                                <div class="rr-actions">
                                    <button class="rr-btn-icon" type="button" aria-label="View review" data-rr-view="{{ base64_encode(json_encode(['title' => $review->title, 'comment' => $review->comment, 'product' => $review->product_name, 'sku' => $review->sku, 'rating' => $review->rating, 'customer' => $review->customer, 'status' => $review->status, 'date' => $review->date.' '.$review->time, 'uuid' => $review->uuid], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"><x-icon name="eye" /></button>
                                    @if($review->status_key !== 'approved')
                                        <form class="rr-action-form" method="post" action="{{ route('admin.reviews.status', $review->id) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button class="rr-btn-icon" type="submit" aria-label="Approve review"><x-icon name="check-circle" /></button>
                                        </form>
                                    @endif
                                    @if($review->status_key !== 'rejected')
                                        <form class="rr-action-form" method="post" action="{{ route('admin.reviews.status', $review->id) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="rr-btn-icon" type="submit" aria-label="Reject review"><x-icon name="x-circle" /></button>
                                        </form>
                                    @endif
                                    @if($review->status_key !== 'flagged')
                                        <form class="rr-action-form" method="post" action="{{ route('admin.reviews.status', $review->id) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="flagged">
                                            <button class="rr-btn-icon" type="submit" aria-label="Flag review"><x-icon name="flag" /></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="padding:32px; text-align:center; color:var(--text-muted);">No reviews match the current filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="rr-pagination">
                <div>Showing {{ $reviews->firstItem() ?: 0 }} to {{ $reviews->lastItem() ?: 0 }} of {{ number_format($reviews->total()) }} reviews</div>
                <div class="rr-page-controls">
                    @if($reviews->onFirstPage())
                        <span class="rr-page-btn" aria-disabled="true"><x-icon name="chevron-left" size="14" /></span>
                    @else
                        <a class="rr-page-btn" href="{{ $reviews->previousPageUrl() }}" aria-label="Previous page"><x-icon name="chevron-left" size="14" /></a>
                    @endif
                    @foreach($reviews->getUrlRange(max(1, $reviews->currentPage() - 2), min($reviews->lastPage(), $reviews->currentPage() + 2)) as $page => $url)
                        <a class="rr-page-btn {{ $page === $reviews->currentPage() ? 'active' : '' }}" href="{{ $url }}">{{ $page }}</a>
                    @endforeach
                    @if($reviews->hasMorePages())
                        <a class="rr-page-btn" href="{{ $reviews->nextPageUrl() }}" aria-label="Next page"><x-icon name="chevron-right" size="14" /></a>
                    @else
                        <span class="rr-page-btn" aria-disabled="true"><x-icon name="chevron-right" size="14" /></span>
                    @endif
                </div>
                <form class="rr-per-page-form" method="get" action="{{ $reviewUrl() }}">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                    @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if($rating)<input type="hidden" name="rating" value="{{ $rating }}">@endif
                    <label class="rr-select">Reviews per page
                        <select name="per_page" aria-label="Reviews per page" onchange="this.form.submit()">
                            @foreach([8, 16, 32] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach
                        </select>
                    </label>
                </form>
            </div>

            <!-- Bottom Widgets -->
            <div class="rr-bottom-grid">
                <!-- Analytics -->
                <div class="rr-widget">
                    <h3>Review Analytics (Last 30 Days)</h3>
                    <div class="rr-analytics-stats">
                        <div>
                            <span>New Reviews</span>
                            <strong>{{ number_format($analytics['new_reviews']) }}</strong>
                            <small>Recorded in the last 30 days</small>
                        </div>
                        <div>
                            <span>Average Rating</span>
                            <strong>{{ number_format($analytics['average_rating'], 1) }} <span style="font-size:12px;font-weight:normal;color:var(--text-muted)">/5</span></strong>
                            <small>Last 30 days</small>
                        </div>
                        <div>
                            <span>Approved Rate</span>
                            <strong>{{ number_format($analytics['approved_rate'], 1) }}%</strong>
                            <small>Approved in the last 30 days</small>
                        </div>
                        <div>
                            <span>Moderation Queue</span>
                            <strong>{{ number_format($analytics['moderation_queue']) }}</strong>
                            <small>Current pending records</small>
                        </div>
                    </div>
                    <div class="rr-analytics-chart" aria-label="Daily review counts for the last 30 days">
                        @php($maxDailyReviews = max(1, max(array_column($dailyAnalytics, 'count'))))
                        @foreach($dailyAnalytics as $day)
                            <span class="rr-chart-bar" title="{{ $day['label'] }}: {{ $day['count'] }} reviews" style="height:{{ max(4, round(($day['count'] / $maxDailyReviews) * 100)) }}%"></span>
                        @endforeach
                    </div>
                    <div class="rr-chart-caption">
                        <span>Daily review submissions</span><span>30 days · record-backed</span>
                    </div>
                </div>

                <!-- Settings -->
                <div class="rr-widget">
                    <h3>Review Settings</h3>
                    <form method="post" action="{{ route('admin.settings.save', 'application-settings') }}" class="rr-settings-form">
                        @csrf
                        <input type="hidden" name="tab" value="features">
                        @foreach([
                            'auto_approve_reviews' => 'Auto approve reviews',
                            'require_review_approval' => 'Require review approval',
                            'allow_review_photos' => 'Allow photos in reviews',
                            'allow_review_videos' => 'Allow video in reviews',
                            'verified_purchases_only' => 'Verified purchases only',
                        ] as $settingKey => $settingLabel)
                            <label class="rr-toggle-row">
                                <span>{{ $settingLabel }}</span>
                                <span class="rr-toggle {{ $reviewBool($settingKey) ? 'active' : '' }}">
                                    <input type="hidden" name="{{ $settingKey }}" value="0">
                                    <input type="checkbox" name="{{ $settingKey }}" value="1" @checked($reviewBool($settingKey)) aria-label="{{ $settingLabel }}">
                                </span>
                            </label>
                        @endforeach
                        <label class="rr-toggle-row"><span>Minimum rating to publish</span>
                            <select class="rr-select" name="minimum_review_rating" aria-label="Minimum rating to publish">
                                @for($minimumRating = 0; $minimumRating <= 5; $minimumRating++)
                                    <option value="{{ $minimumRating }}" @selected((int) $reviewSetting('minimum_review_rating', 0) === $minimumRating)>{{ $minimumRating === 0 ? 'All ratings' : $minimumRating.' star'.($minimumRating === 1 ? '' : 's').' and above' }}</option>
                                @endfor
                            </select>
                        </label>
                        <button class="rr-manage-btn" type="submit">Save Review Settings</button>
                    </form>
                    <a class="rr-settings-link" href="{{ route('admin.settings.page', ['section' => 'application-settings', 'tab' => 'features']) }}">Open full application settings <x-icon name="arrow-right" size="12" /></a>
                </div>

                <!-- Guidelines -->
                <div class="rr-widget">
                    <h3>Review Guidelines</h3>
                    <div class="rr-guideline">
                        <x-icon name="check-circle" />
                        <span>Be honest and respectful</span>
                    </div>
                    <div class="rr-guideline">
                        <x-icon name="check-circle" />
                        <span>Focus on the product experience</span>
                    </div>
                    <div class="rr-guideline">
                        <x-icon name="x-circle" style="color:#dc2626;" />
                        <span>No personal or promotional content</span>
                    </div>
                    <div class="rr-guideline">
                        <x-icon name="x-circle" style="color:#dc2626;" />
                        <span>Do not include contact information</span>
                    </div>
                    <button class="rr-manage-btn" style="margin-top:20px;" type="button" data-rr-guidelines>View Guidelines</button>
                </div>

                <!-- Top Products -->
                <div class="rr-widget">
                    <h3>Top Reviewed Products</h3>
                    @forelse($topProducts as $topProduct)
                        <div class="rr-top-product">
                            @if($topProduct->product?->image)
                                <img class="rr-product-img" src="{{ \Illuminate\Support\Str::startsWith($topProduct->product->image, ['http://', 'https://', '/']) ? $topProduct->product->image : \Illuminate\Support\Facades\Storage::disk('public')->url($topProduct->product->image) }}" alt="{{ $topProduct->product->name }}">
                            @else
                                <div class="rr-product-img" role="img" aria-label="No product image"></div>
                            @endif
                            <div>
                                @if($topProduct->product)
                                    <h4><a href="{{ route('product', $topProduct->product) }}">{{ $topProduct->product->name }}</a></h4>
                                @else
                                    <h4>Deleted product</h4>
                                @endif
                                <div class="rr-top-product-rating">
                                    <x-icon name="star" /> {{ number_format((float) $topProduct->average_rating, 1) }} <span style="font-size:10px;">({{ number_format((int) $topProduct->reviews_count) }} reviews)</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p style="font-size:12px; color:var(--text-muted);">No product reviews yet.</p>
                    @endforelse
                    <a class="rr-manage-btn" href="{{ route('admin.resource', ['module' => 'reviews-ratings']) }}">View All Product Reviews</a>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="rr-sidebar">
            <!-- Breakdown -->
            <div>
                <h3>RATINGS BREAKDOWN</h3>
                <div style="display:flex; align-items:end; gap:8px; margin-bottom:12px;">
                    <span style="font-size:32px; font-weight:700;">{{ number_format($stats['average_rating'], 1) }}</span>
                    <div>
                        <div style="color:var(--star-gold); margin-bottom:4px;">★★★★★</div>
                        <div style="font-size:11px; color:var(--text-muted);">Based on {{ number_format($stats['total']) }} reviews</div>
                    </div>
                </div>
                @foreach($ratingBreakdown as $stars => $breakdown)
                    <div class="rr-breakdown-row">
                        <div class="rr-breakdown-label">{{ $stars }} Star{{ $stars === 1 ? '' : 's' }}</div>
                        <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:{{ $breakdown['percentage'] }}%; background:{{ $stars >= 4 ? '#059669' : ($stars === 3 ? 'var(--star-gold)' : '#3b82f6') }};"></div></div>
                        <div class="rr-breakdown-val">{{ number_format($breakdown['count']) }} ({{ number_format($breakdown['percentage'], 1) }}%)</div>
                    </div>
                @endforeach
            </div>

            <!-- Sources -->
            <div>
                <h3>REVIEW SOURCES</h3>
                <div style="display:flex; gap:16px; align-items:center;">
                    <div class="rr-sources-list" style="flex:1;">
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#059669;"></div> Website</div>
                            <div style="color:var(--text-muted);">{{ number_format($stats['total']) }} (100%)</div>
                        </div>
                    </div>
                    <div style="width:80px; height:80px; border-radius:50%; border:12px solid #059669; border-top-color:#3b82f6; border-right-color:#f97316; display:flex; align-items:center; justify-content:center; flex-direction:column; position:relative;">
                        <span style="font-weight:700; font-size:14px; position:absolute;">{{ number_format($stats['total']) }}</span>
                        <span style="font-size:9px; color:var(--text-muted); position:absolute; bottom:12px;">Total</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <h3>REVIEW WORKFLOWS</h3>
                <div class="rr-quick-actions">
                    <a href="{{ route('admin.resource', ['module' => 'reviews-ratings', 'status' => 'approved']) }}" class="rr-action-link"><x-icon name="check-circle" /> View approved reviews</a>
                    <a href="{{ route('admin.resource', ['module' => 'reviews-ratings', 'status' => 'pending']) }}" class="rr-action-link"><x-icon name="archive" /> Review pending moderation</a>
                    <a href="{{ route('admin.resource', ['module' => 'reviews-ratings', 'status' => 'flagged']) }}" class="rr-action-link"><x-icon name="flag" /> Review flagged items</a>
                    <a href="{{ route('admin.resource', ['module' => 'reviews-ratings']) }}" class="rr-action-link"><x-icon name="refresh-cw" /> Reset review filters</a>
                </div>
            </div>

            <!-- UUID Traceability -->
            <div>
                <h3>UUID TRACEABILITY</h3>
                <p class="rr-uuid-text">Every review is assigned a unique UUID for full traceability.</p>
                <div style="font-size:11px; font-weight:600; margin-bottom:4px;">Last Review UUID</div>
                <div class="rr-uuid-code">{{ $lastReviewUuid ?: '—' }}</div>
                <a class="rr-manage-btn" style="display:flex; justify-content:space-between; align-items:center;" href="{{ route('admin.resource', ['module' => 'audit-logs']) }}">
                    View Audit Log Records <x-icon name="chevron-right" size="14" />
                </a>
            </div>
        </div>
    </div>
</div>

<dialog class="rr-dialog" data-rr-dialog aria-labelledby="rr-dialog-title">
    <div class="rr-dialog-card">
        <header><div><span class="rr-dialog-eyebrow">Review record</span><h2 id="rr-dialog-title" data-rr-detail-title>Review details</h2></div><button type="button" class="rr-btn-icon" data-rr-dialog-close aria-label="Close review details">×</button></header>
        <dl class="rr-dialog-details">
            <div><dt>Customer</dt><dd data-rr-detail-customer>—</dd></div>
            <div><dt>Product</dt><dd data-rr-detail-product>—</dd></div>
            <div><dt>Rating</dt><dd data-rr-detail-rating>—</dd></div>
            <div><dt>Status</dt><dd data-rr-detail-status>—</dd></div>
            <div><dt>Submitted</dt><dd data-rr-detail-date>—</dd></div>
            <div><dt>UUID</dt><dd data-rr-detail-uuid>—</dd></div>
        </dl>
        <div class="rr-dialog-comment"><h3>Comment</h3><p data-rr-detail-comment>—</p></div>
        <button type="button" class="rr-manage-btn" data-rr-dialog-close>Close</button>
    </div>
</dialog>
<dialog class="rr-dialog" data-rr-guidelines-dialog aria-labelledby="rr-guidelines-title">
    <div class="rr-dialog-card">
        <header><div><span class="rr-dialog-eyebrow">Moderation policy</span><h2 id="rr-guidelines-title">Review guidelines</h2></div><button type="button" class="rr-btn-icon" data-rr-guidelines-close aria-label="Close review guidelines">×</button></header>
        <ul class="rr-dialog-list">
            <li>Keep feedback honest, respectful and focused on the product experience.</li>
            <li>Do not publish personal contact information, promotional material or abusive content.</li>
            <li>Use Flagged for moderation concerns and keep rejected records available for audit review.</li>
            <li>Imported and newly submitted reviews remain pending until an administrator approves them.</li>
        </ul>
        <button type="button" class="rr-manage-btn" data-rr-guidelines-close>Close</button>
    </div>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/reviews-ratings.js?v=20260913-b20-1') }}" defer></script>
@endpush
