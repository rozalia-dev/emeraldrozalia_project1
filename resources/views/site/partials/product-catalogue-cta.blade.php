@if($productCatalogue)
<style>
.product-catalogue-quick-link{display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:11px 16px;border-radius:8px;background:var(--site-brand-primary,#075b2f);color:#fff!important;text-decoration:none;font-size:.82rem;font-weight:800;letter-spacing:.04em;box-shadow:0 8px 24px rgba(0,0,0,.12)}.collections-heading .product-catalogue-quick-link{margin:0 0 0 auto;white-space:nowrap}@media(max-width:700px){.collections-heading .product-catalogue-quick-link{margin:12px auto 0}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!['/shop','/collections'].includes(window.location.pathname) && window.location.pathname.indexOf('/category/') !== 0) return;
    if (document.querySelector('.product-catalogue-quick-link')) return;

    const link = document.createElement('a');
    link.className = 'product-catalogue-quick-link';
    link.href = @json(route('catalogue.show'));
    link.textContent = 'DOWNLOAD PRODUCT CATALOGUE →';
    link.setAttribute('aria-label', 'Open the Emerald Rozalia product catalogue download page');

    const host = document.querySelector('.shop-hero-content') || document.querySelector('.collections-heading');
    if (host) host.appendChild(link);
});
</script>
@endif
