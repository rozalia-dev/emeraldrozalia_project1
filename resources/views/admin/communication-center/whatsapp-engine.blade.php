@extends('layouts.admin')

@section('title', 'WhatsApp Setup')

@push('styles')
<link rel="stylesheet" href="/css/whatsapp-engine.css?v=20260916-1">
@endpush

@section('content')
<div class="wa-engine" data-wa-engine-root data-status-url="{{ route('admin.communication-center.whatsapp.status') }}" data-qr-url="{{ route('admin.communication-center.whatsapp.qr') }}">
    <header class="wa-engine-header">
        <div>
            <p class="wa-engine-eyebrow">Communication Center · WhatsApp</p>
            <h1>WhatsApp Web Engine</h1>
            <p>Link one WhatsApp account to the private Node.js engine. Incoming messages will appear in the existing WhatsApp Communication Center and replies will use the existing queued delivery pipeline.</p>
        </div>
        <a class="wa-engine-back" href="{{ url('/admin/resource/whatsapp') }}">Open WhatsApp Inbox</a>
    </header>

    <section class="wa-engine-grid">
        <article class="wa-engine-card">
            <div class="wa-engine-card-head">
                <div>
                    <small>ENGINE STATUS</small>
                    <h2>Connection</h2>
                </div>
                <span class="wa-engine-status" data-wa-status>Checking…</span>
            </div>

            <dl class="wa-engine-details">
                <div><dt>Laravel configuration</dt><dd>{{ $configured ? 'Configured' : 'Missing server environment values' }}</dd></div>
                <div><dt>Private engine URL</dt><dd>{{ $engineUrl ?: 'Not configured' }}</dd></div>
                <div><dt>Browser session</dt><dd data-wa-browser-state>Unknown</dd></div>
                <div><dt>Last engine error</dt><dd data-wa-error>—</dd></div>
            </dl>

            <div class="wa-engine-actions">
                <button type="button" data-wa-refresh>Refresh Status</button>
            </div>
        </article>

        <article class="wa-engine-card wa-engine-qr-card">
            <div class="wa-engine-card-head">
                <div>
                    <small>LINKED DEVICE</small>
                    <h2>QR Code</h2>
                </div>
            </div>

            <div class="wa-engine-qr-wrap">
                <img data-wa-qr alt="WhatsApp linked-device QR code" hidden>
                <div class="wa-engine-placeholder" data-wa-qr-placeholder>
                    <strong>Waiting for engine…</strong>
                    <span>The QR code will appear here when the Node service is running and the WhatsApp account is not yet linked.</span>
                </div>
            </div>

            <ol class="wa-engine-steps">
                <li>Open WhatsApp on the phone.</li>
                <li>Go to <b>Linked devices</b>.</li>
                <li>Choose <b>Link a device</b>.</li>
                <li>Scan the QR code shown here.</li>
            </ol>
        </article>
    </section>

    <section class="wa-engine-card wa-engine-flow">
        <div class="wa-engine-card-head">
            <div>
                <small>LIVE FLOW</small>
                <h2>How Project 1 Uses This Engine</h2>
            </div>
        </div>
        <div class="wa-engine-flow-grid">
            <div><b>Incoming</b><span>WhatsApp → Node engine → signed Laravel webhook → Conversation + Message → Communication Center</span></div>
            <div><b>Outgoing</b><span>Communication Center reply → Laravel queue → WhatsApp provider → Node engine → WhatsApp Web</span></div>
            <div><b>Delivery</b><span>WhatsApp acknowledgement → signed delivery webhook → message delivery status in Laravel</span></div>
        </div>
    </section>
</div>

<script>
(() => {
    const root = document.querySelector('[data-wa-engine-root]');
    if (!root) return;

    const statusEl = root.querySelector('[data-wa-status]');
    const browserEl = root.querySelector('[data-wa-browser-state]');
    const errorEl = root.querySelector('[data-wa-error]');
    const qr = root.querySelector('[data-wa-qr]');
    const placeholder = root.querySelector('[data-wa-qr-placeholder]');
    const refresh = root.querySelector('[data-wa-refresh]');

    const setStatus = (label, tone) => {
        statusEl.textContent = label;
        statusEl.dataset.tone = tone;
    };

    async function loadQr() {
        try {
            const response = await fetch(root.dataset.qrUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (data.qr) {
                qr.src = data.qr;
                qr.hidden = false;
                placeholder.hidden = true;
            } else {
                qr.hidden = true;
                placeholder.hidden = false;
                placeholder.querySelector('strong').textContent = data.ready ? 'WhatsApp is linked.' : 'Waiting for QR code…';
            }
        } catch (_) {
            qr.hidden = true;
            placeholder.hidden = false;
        }
    }

    async function loadStatus() {
        setStatus('Checking…', 'neutral');
        try {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            browserEl.textContent = data.whatsapp_state || data.state || 'Unknown';
            errorEl.textContent = data.last_error || data.error || '—';

            if (data.ready) {
                setStatus('Connected', 'success');
                qr.hidden = true;
                placeholder.hidden = false;
                placeholder.querySelector('strong').textContent = 'WhatsApp is linked.';
            } else if (response.status === 503) {
                setStatus('Engine Offline', 'danger');
                await loadQr();
            } else {
                setStatus('Action Required', 'warning');
                await loadQr();
            }
        } catch (_) {
            browserEl.textContent = 'Unavailable';
            errorEl.textContent = 'Laravel cannot reach the private Node engine.';
            setStatus('Engine Offline', 'danger');
        }
    }

    refresh?.addEventListener('click', loadStatus);
    loadStatus();
    window.setInterval(loadStatus, 5000);
})();
</script>
@endsection
