# cPanel Operations Evidence

## Scope

This change functionalises the shared operational surfaces visible in the Project 1 cPanel reference:

- Online Sales and the other shared record modules support create, search, status/date filtering, edit and delete.
- Every generic record mutation writes an audit entry.
- The header search submits to an authenticated global search covering products, records, orders, franchise applications, Communication Center conversations and retail stores.
- Communication Center supports search/status filters, assignment, priority, follow-up time and saved outbound replies.
- Product Manager links to a real edit form that updates the existing product and preserves SKU/slug uniqueness.
- The separate `franchise_portal` channel and separate Franchise Portal module remain excluded; franchise work stays in the shared cPanel.

## Acceptance coverage

The feature coverage is in `tests/Feature/ProjectScopeTest.php`:

- `test_online_sales_resource_supports_audited_create_search_update_and_delete`
- `test_communication_center_can_update_workflow_and_save_a_reply`
- `test_admin_global_search_returns_project_one_records`
- `test_product_manager_edit_updates_the_existing_product_without_creating_a_duplicate`
- `test_add_product_uses_the_product_workflow_and_shared_admin_shell`

## Verification status

Static checks completed in the review workspace:

- `git diff --check` — passed.
- `node --check public/js/app.js` — passed.
- No `php`, `composer` or `docker` binary is available locally.

The PHP feature suite, migrations, route compilation and Docker deployment checks must be confirmed by GitHub Actions after push. This file does not claim those checks passed until their run result is available.
