<link rel="stylesheet" href="/css/chat-24-7.css?v=20260916-visible-service-actions-1">
<div
    class="chat24"
    data-chat-24-7-widget
    data-start-url="{{ route('chat24.start') }}"
    data-base-url="{{ url('/chat-24-7') }}"
    data-csrf="{{ csrf_token() }}"
    data-product-slug="{{ $contextProductSlug }}"
>
    <button class="chat24-launcher" type="button" data-chat24-toggle aria-expanded="false" aria-controls="chat24-panel">
        <span class="chat24-launcher-dot" aria-hidden="true"></span>
        <span>Chat 24/7</span>
    </button>

    <section class="chat24-panel" id="chat24-panel" hidden aria-label="Emerald Rozalia 24/7 product and business assistant">
        <header class="chat24-header">
            <div>
                <strong>Emerald Rozalia AI Assistant</strong>
                <span><i></i> Online 24/7 · Product &amp; application support</span>
            </div>
            <button type="button" data-chat24-close aria-label="Close chat">×</button>
        </header>

        <div class="chat24-messages" data-chat24-messages aria-live="polite"></div>
        <div class="chat24-products" data-chat24-products hidden></div>
        <div class="chat24-quick" data-chat24-quick aria-label="Quick chat options"></div>

        <div class="chat24-service-actions" aria-label="Customer service actions">
            <a class="chat24-service-action chat24-book" href="{{ route('contact') }}#contact-schedule">
                Book Appointment
            </a>
            <button type="button" class="chat24-service-action chat24-connect" data-chat24-human>
                Connect to a Person
            </button>
        </div>

        <div class="chat24-status" data-chat24-status></div>

        <form class="chat24-form" data-chat24-form>
            <label class="sr-only" for="chat24-message">Message</label>
            <textarea id="chat24-message" data-chat24-input rows="2" maxlength="2500" placeholder="Ask about products, bulk/corporate orders, franchise, requirements or book an appointment..."></textarea>
            <div class="chat24-form-actions">
                <span class="chat24-form-hint">Replies from a person appear in this same chat.</span>
                <button type="submit" class="chat24-send">Send</button>
            </div>
        </form>
    </section>
</div>
<script src="/js/chat-24-7.js?v=20260916-visible-service-actions-1" defer></script>
