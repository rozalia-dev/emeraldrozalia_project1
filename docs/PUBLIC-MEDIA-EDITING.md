# Public page media workflow

Public page imagery is controlled through approved media records. A page section stores a media UUID, not a public file path, so replacing an image does not require editing Blade templates or moving files on the server.

## Change a homepage or public-page image

1. Sign in to cPanel and open **Website & Products → Media Manager** (`/admin/resource/site-media`).
2. Upload the approved original. Complete the asset name, alternative text, and—when needed—the focal point or crop. The manager validates the file type, dimensions, and configured upload limit, then keeps the new asset pending.
3. Review the preview and choose **Approve**. Only approved, active public assets can be delivered by the public media route.
4. Open **Website & Products → Pages**, edit the public page, and open **Page sections**.
5. In the relevant block, choose the approved asset from **Approved public media (select after approval)**, save the page, and publish the revision. Use **Preview** before publishing.

The homepage hero, heritage, quality, and franchise blocks use the selected section media. Collection cards can select their own media in the card JSON. Homepage product cards use the approved image attached to each live product, so change those through the product media workflow rather than the page hero selector.

The supplied homepage artwork is the baseline shown when a homepage block has no selected media UUID. Selecting an approved asset replaces that baseline while keeping the section’s layout, links, alt text, responsive behavior, and audit trail intact.

## Replace or recover an existing asset

From Media Manager, use **Replace file** on the existing asset to create a new version. The replacement is pending until it is reviewed and approved. The previous version remains available for version restore. Archive or trash an asset only after checking its usage; referenced assets are protected from unsafe permanent deletion.

Do not paste `/storage/...` paths into page content and do not use a screenshot as a substitute for an approved original. Public delivery is through the controlled UUID media route, which also supplies responsive derivatives where available.

## Change the shared header or footer

The homepage and Contact page now consume the same public shell. To update the shared menu, logo reference, footer columns, social links, or legal links, open **Website & Products → Pages → Shared Layouts** (`/admin/pages/layouts`). Edit a draft layout, validate it, submit it for approval, approve it, and activate it. The active public snapshot is then used by every public page that extends the shared site layout.

Contact remains a footer-managed destination; it is not added to the primary header menu. Keep the approved eight-item header navigation and use the footer’s **Customer Care → Contact Us** link for that route.
