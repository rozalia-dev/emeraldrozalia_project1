# Corporate Order Reference Implementation Evidence

Reference supplied by the user on 8 September 2026: `corporate order page.png` (1024 × 1536).

## Visual contract
- Dark green / black Emerald Rozalia corporate-order presentation.
- Corporate Orders hero with premium branded cap photography and presentation packaging.
- Premium Quality, Custom Branding & Embroidery, Bulk Pricing & Discounts, and Worldwide Delivery commitments.
- Corporate Uniforms, Events & Promotions, Sports Teams, Hospitality & Retail, Schools & Colleges, and Gifts & Merchandise use cases.
- Five-stage process: Enquire, Design, Approve, Produce, Deliver.
- What We Offer product presentation cards.
- Request A Quote panel with WhatsApp alternative and response assurances.
- Why Choose Emerald Rozalia and trusted organisations sections.
- Corporate footer with contact information only in the footer.

## Functional contract
- `GET /corporate-orders` renders the dedicated reference-aligned Laravel view.
- Quote requests validate name, email, and requirements.
- Company, phone, and country are captured when supplied.
- Successful requests persist to `Inquiry`.
- Successful requests also create a `Conversation` and inbound message in the unified Communication Centre.
- Responsive layouts are defined for desktop, tablet, and mobile.
- The approved visual is stored as an approved PNG reference asset and used only for the photographic/reference areas; the page structure and quote form remain real HTML controls.
