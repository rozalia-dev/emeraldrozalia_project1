@extends('layouts.admin')
@section('title', 'Reviews & Ratings')
@section('content')
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
</style>

<div class="rr-dashboard">
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
                    <strong>Thursday, 1 May 2025</strong>
                </div>
            </div>
            <div class="rr-time">11:45 AM</div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rr-kpi-grid">
        <div class="rr-kpi-card">
            <div class="rr-kpi-icon bg-green"><x-icon name="star" /></div>
            <div class="rr-kpi-content">
                <h3>Average Rating</h3>
                <p class="rr-kpi-val">4.7 <span style="font-size:14px;color:var(--text-muted);font-weight:400;">/ 5</span></p>
                <div class="rr-kpi-trend up"><x-icon name="arrow-up" size="12" /> 0.2 <span>vs last 30 days</span></div>
            </div>
        </div>
        <div class="rr-kpi-card">
            <div class="rr-kpi-icon bg-purple"><x-icon name="message-square" /></div>
            <div class="rr-kpi-content">
                <h3>Total Reviews</h3>
                <p class="rr-kpi-val">2,458</p>
                <div class="rr-kpi-trend up"><x-icon name="arrow-up" size="12" /> 18.6% <span>vs last 30 days</span></div>
            </div>
        </div>
        <div class="rr-kpi-card">
            <div class="rr-kpi-icon bg-orange"><x-icon name="archive" /></div>
            <div class="rr-kpi-content">
                <h3>Pending Reviews</h3>
                <p class="rr-kpi-val">42</p>
                <div class="rr-kpi-trend down"><x-icon name="arrow-down" size="12" /> 12.5% <span>vs last 30 days</span></div>
            </div>
        </div>
        <div class="rr-kpi-card">
            <div class="rr-kpi-icon bg-blue"><x-icon name="check-circle" /></div>
            <div class="rr-kpi-content">
                <h3>Approved Reviews</h3>
                <p class="rr-kpi-val">2,316</p>
                <div class="rr-kpi-trend up"><x-icon name="arrow-up" size="12" /> 20.3% <span>vs last 30 days</span></div>
            </div>
        </div>
        <div class="rr-kpi-card">
            <div class="rr-kpi-icon bg-emerald"><x-icon name="shield" /></div>
            <div class="rr-kpi-content">
                <h3>Flagged / Reported</h3>
                <p class="rr-kpi-val">18</p>
                <div class="rr-kpi-trend down"><x-icon name="arrow-down" size="12" /> 10.0% <span>vs last 30 days</span></div>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="rr-layout">
        <!-- Left Column -->
        <div class="rr-main-col">
            <!-- Tabs -->
            <div class="rr-tabs">
                <div class="rr-tab active">All Reviews</div>
                <div class="rr-tab">Pending Approval</div>
                <div class="rr-tab">Approved</div>
                <div class="rr-tab">Disapproved / Rejected</div>
                <div class="rr-tab">Flagged</div>
                <div class="rr-tab">Q&A</div>
                <div class="rr-tab">Import Reviews</div>
            </div>

            <!-- Toolbar -->
            <div class="rr-toolbar">
                <div class="rr-search">
                    <x-icon name="search" />
                    <input type="text" placeholder="Search reviews...">
                </div>
                <button class="rr-filter-btn"><x-icon name="filter" size="14" /> Filters</button>
                <select class="rr-select"><option>All Products</option></select>
                <select class="rr-select"><option>All Ratings</option></select>
                <select class="rr-select"><option>All Statuses</option></select>
                <select class="rr-select"><option>All Sources</option></select>
                <button class="rr-reset-btn"><x-icon name="refresh-cw" size="12" /> Reset</button>
            </div>

            <!-- Table -->
            <div class="rr-table-wrap">
                <table class="rr-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox"></th>
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
                        @foreach($reviews as $review)
                        <tr>
                            <td><input type="checkbox"></td>
                            <td class="rr-td-review">
                                <h4>{{ $review->title }}</h4>
                                <p>{{ $review->comment }}</p>
                            </td>
                            <td class="rr-td-product">
                                <div class="rr-product-img"></div>
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
                                <span class="rr-badge {{ strtolower($review->status) }}">{{ $review->status }}</span>
                            </td>
                            <td class="rr-date">
                                <strong>{{ $review->date }}</strong>
                                <span>{{ $review->time }}</span>
                            </td>
                            <td>
                                <div class="rr-actions">
                                    <button class="rr-btn-icon"><x-icon name="eye" /></button>
                                    <button class="rr-btn-icon"><x-icon name="message-square" /></button>
                                    <button class="rr-btn-icon"><x-icon name="flag" /></button>
                                    <button class="rr-btn-icon"><x-icon name="more-vertical" /></button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="rr-pagination">
                <div>Showing 1 to 8 of 2,458 reviews</div>
                <div class="rr-page-controls">
                    <button class="rr-page-btn"><x-icon name="chevron-left" size="14" /></button>
                    <button class="rr-page-btn active">1</button>
                    <button class="rr-page-btn">2</button>
                    <button class="rr-page-btn">3</button>
                    <span>...</span>
                    <button class="rr-page-btn">308</button>
                    <button class="rr-page-btn"><x-icon name="chevron-right" size="14" /></button>
                </div>
                <select class="rr-select"><option>8 / page</option></select>
            </div>

            <!-- Bottom Widgets -->
            <div class="rr-bottom-grid">
                <!-- Analytics -->
                <div class="rr-widget">
                    <h3>Review Analytics (Last 30 Days)</h3>
                    <div class="rr-analytics-stats">
                        <div>
                            <span>New Reviews</span>
                            <strong>386</strong>
                            <small>↑ 18.6%</small>
                        </div>
                        <div>
                            <span>Average Rating</span>
                            <strong>4.7 <span style="font-size:12px;font-weight:normal;color:var(--text-muted)">/5</span></strong>
                            <small>↑ 0.2</small>
                        </div>
                        <div>
                            <span>Review Views</span>
                            <strong>12,845</strong>
                            <small>↑ 22.1%</small>
                        </div>
                        <div>
                            <span>Conversion from Reviews</span>
                            <strong>8.3%</strong>
                            <small>↑ 0.9%</small>
                        </div>
                    </div>
                    <!-- Placeholder Chart SVG -->
                    <div style="height:60px; border-bottom:1px solid var(--border-color); position:relative;">
                        <svg width="100%" height="100%" viewBox="0 0 300 60" preserveAspectRatio="none">
                            <path d="M0,50 L30,40 L60,45 L90,20 L120,30 L150,15 L180,25 L210,10 L240,15 L270,5 L300,20" fill="none" stroke="#3b82f6" stroke-width="2"/>
                            <path d="M0,55 L30,48 L60,50 L90,40 L120,45 L150,30 L180,35 L210,25 L240,30 L270,15 L300,25" fill="none" stroke="#10b981" stroke-width="2" stroke-dasharray="4"/>
                        </svg>
                    </div>
                    <div style="display:flex; justify-content:center; gap:16px; margin-top:8px; font-size:10px; color:var(--text-muted)">
                        <span><span style="color:#3b82f6;font-weight:bold;">—</span> New Reviews</span>
                        <span><span style="color:#10b981;font-weight:bold;">- -</span> Review Views</span>
                    </div>
                </div>

                <!-- Settings -->
                <div class="rr-widget">
                    <h3>Review Settings</h3>
                    <div class="rr-toggle-row">
                        <span>Auto Approve Reviews</span>
                        <div class="rr-toggle"></div>
                    </div>
                    <div class="rr-toggle-row">
                        <span>Require Review Approval</span>
                        <div class="rr-toggle active"></div>
                    </div>
                    <div class="rr-toggle-row">
                        <span>Allow Photos in Reviews</span>
                        <div class="rr-toggle active"></div>
                    </div>
                    <div class="rr-toggle-row">
                        <span>Allow Video in Reviews</span>
                        <div class="rr-toggle active"></div>
                    </div>
                    <div class="rr-toggle-row">
                        <span>Display Only Verified Purchases</span>
                        <div class="rr-toggle active"></div>
                    </div>
                    <div class="rr-toggle-row">
                        <span>Minimum Rating to Publish</span>
                        <select class="rr-select" style="padding:2px 24px 2px 8px; font-size:11px;"><option>All Ratings</option></select>
                    </div>
                    <button class="rr-manage-btn">Manage Settings</button>
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
                    <button class="rr-manage-btn" style="margin-top:20px;">View Guidelines</button>
                </div>

                <!-- Top Products -->
                <div class="rr-widget">
                    <h3>Top Reviewed Products</h3>
                    <div class="rr-top-product">
                        <div class="rr-product-img"></div>
                        <div>
                            <h4>Emerald Signature Cap</h4>
                            <div class="rr-top-product-rating">
                                <x-icon name="star" /> 4.8 <span style="font-size:10px;">(150 reviews)</span>
                            </div>
                        </div>
                    </div>
                    <div class="rr-top-product">
                        <div class="rr-product-img"></div>
                        <div>
                            <h4>Luxury Baseball Cap</h4>
                            <div class="rr-top-product-rating">
                                <x-icon name="star" /> 4.7 <span style="font-size:10px;">(98 reviews)</span>
                            </div>
                        </div>
                    </div>
                    <div class="rr-top-product">
                        <div class="rr-product-img"></div>
                        <div>
                            <h4>Premium Bucket Hat</h4>
                            <div class="rr-top-product-rating">
                                <x-icon name="star" /> 4.6 <span style="font-size:10px;">(75 reviews)</span>
                            </div>
                        </div>
                    </div>
                    <button class="rr-manage-btn">View All Product Reviews</button>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="rr-sidebar">
            <!-- Breakdown -->
            <div>
                <h3>RATINGS BREAKDOWN</h3>
                <div style="display:flex; align-items:end; gap:8px; margin-bottom:12px;">
                    <span style="font-size:32px; font-weight:700;">4.7</span>
                    <div>
                        <div style="color:var(--star-gold); margin-bottom:4px;">★★★★★</div>
                        <div style="font-size:11px; color:var(--text-muted);">Based on 2,458 reviews</div>
                    </div>
                </div>
                <div class="rr-breakdown-row">
                    <div class="rr-breakdown-label">5 Stars</div>
                    <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:70.9%; background:#059669;"></div></div>
                    <div class="rr-breakdown-val">1,742 (70.9%)</div>
                </div>
                <div class="rr-breakdown-row">
                    <div class="rr-breakdown-label">4 Stars</div>
                    <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:20.3%; background:#059669;"></div></div>
                    <div class="rr-breakdown-val">498 (20.3%)</div>
                </div>
                <div class="rr-breakdown-row">
                    <div class="rr-breakdown-label">3 Stars</div>
                    <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:5.8%; background:var(--star-gold);"></div></div>
                    <div class="rr-breakdown-val">142 (5.8%)</div>
                </div>
                <div class="rr-breakdown-row">
                    <div class="rr-breakdown-label">2 Stars</div>
                    <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:2.0%; background:#059669;"></div></div>
                    <div class="rr-breakdown-val">48 (2.0%)</div>
                </div>
                <div class="rr-breakdown-row">
                    <div class="rr-breakdown-label">1 Star</div>
                    <div class="rr-breakdown-bar"><div class="rr-breakdown-fill" style="width:1.0%; background:#059669;"></div></div>
                    <div class="rr-breakdown-val">28 (1.0%)</div>
                </div>
            </div>

            <!-- Sources -->
            <div>
                <h3>REVIEW SOURCES</h3>
                <div style="display:flex; gap:16px; align-items:center;">
                    <div class="rr-sources-list" style="flex:1;">
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#059669;"></div> Website</div>
                            <div style="color:var(--text-muted);">1,682 (68.5%)</div>
                        </div>
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#3b82f6;"></div> Google</div>
                            <div style="color:var(--text-muted);">512 (20.9%)</div>
                        </div>
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#f97316;"></div> WhatsApp</div>
                            <div style="color:var(--text-muted);">156 (6.3%)</div>
                        </div>
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#8b5cf6;"></div> Email</div>
                            <div style="color:var(--text-muted);">78 (3.2%)</div>
                        </div>
                        <div class="rr-source-item">
                            <div class="rr-source-label"><div class="rr-source-dot" style="background:#9ca3af;"></div> Other</div>
                            <div style="color:var(--text-muted);">30 (1.1%)</div>
                        </div>
                    </div>
                    <div style="width:80px; height:80px; border-radius:50%; border:12px solid #059669; border-top-color:#3b82f6; border-right-color:#f97316; display:flex; align-items:center; justify-content:center; flex-direction:column; position:relative;">
                        <span style="font-weight:700; font-size:14px; position:absolute;">2,458</span>
                        <span style="font-size:9px; color:var(--text-muted); position:absolute; bottom:12px;">Total</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <h3>QUICK ACTIONS</h3>
                <div class="rr-quick-actions">
                    <a href="#" class="rr-action-link"><x-icon name="check-circle" /> Approve Selected Reviews</a>
                    <a href="#" class="rr-action-link"><x-icon name="x-circle" /> Disapprove Selected Reviews</a>
                    <a href="#" class="rr-action-link"><x-icon name="message-square" /> Reply to Selected Reviews</a>
                    <a href="#" class="rr-action-link"><x-icon name="refresh-cw" /> Bulk Update Status</a>
                    <a href="#" class="rr-action-link"><x-icon name="download" /> Export Reviews</a>
                    <a href="#" class="rr-action-link"><x-icon name="upload" /> Import Reviews</a>
                    <a href="#" class="rr-action-link"><x-icon name="file-text" /> Review Guidelines</a>
                    <a href="#" class="rr-action-link"><x-icon name="settings" /> Review Settings</a>
                </div>
            </div>

            <!-- UUID Traceability -->
            <div>
                <h3>UUID TRACEABILITY</h3>
                <p class="rr-uuid-text">Every review is assigned a unique UUID for full traceability.</p>
                <div style="font-size:11px; font-weight:600; margin-bottom:4px;">Last Review UUID</div>
                <div class="rr-uuid-code">3a2f6c78-91d5-4bd2-b5f0-0e2d7a9c1e3a <x-icon name="copy" size="12" style="cursor:pointer; color:var(--text-muted); margin-left:4px;" /></div>
                <button class="rr-manage-btn" style="display:flex; justify-content:space-between; align-items:center;">
                    View Reviews Audit Log <x-icon name="chevron-right" size="14" />
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
