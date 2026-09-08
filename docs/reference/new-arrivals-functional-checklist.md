# New Arrivals Functional Checklist

- `/new-arrivals` route remains live.
- Category, colour, material and maximum-price filters submit with GET and can be combined.
- Newest, price-low, price-high and name sorting are supported.
- Active `is_new` products paginate 12 per page.
- Product cards link to the real product page.
- Add-to-cart submits a CSRF-protected POST to the cart route.
- Virtual Try-On callout links to the live studio.
- Newsletter form submits through the public inquiry endpoint so the request enters the Communication Centre.
- Responsive desktop/tablet/mobile styling is isolated in `public/css/new-arrivals.css`.
