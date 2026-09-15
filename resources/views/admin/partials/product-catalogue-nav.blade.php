<script>
document.addEventListener('DOMContentLoaded', function () {
    const href = @json(route('admin.product-catalogue.index'));
    const links = Array.from(document.querySelectorAll('.admin-sidebar a'));
    if (links.some(function (link) { return new URL(link.href, window.location.origin).pathname === new URL(href, window.location.origin).pathname; })) return;

    const anchor = links.find(function (link) { return link.textContent.trim() === 'Bulk Product Upload'; });
    if (!anchor) return;

    const link = document.createElement('a');
    link.href = href;
    link.className = window.location.pathname.indexOf('/admin/resource/product-catalogue') === 0 ? 'active' : '';
    link.innerHTML = '<span>Product Catalogue</span>';
    anchor.insertAdjacentElement('afterend', link);
});
</script>
