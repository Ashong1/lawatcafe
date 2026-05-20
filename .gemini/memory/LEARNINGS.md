# Project Learnings & Retrospectives

### 2026-05-20 - Modernizing UI: Text to Icons Transition
- **Action**: Replaced text-based action buttons (Edit, Delete, Save, etc.) with Lucide icons across all management views.
- **Goal**: Improve UI cleanliness and adhere to modern design standards.
- **Outcome**: 
  - Standardized the use of `x-lucide-pencil` for editing and `x-lucide-trash-2` for deletion.
  - Added `hover:bg-amber-100` and `hover:bg-red-50` for better interactive feedback on icon buttons.
  - Improved accessibility by adding `title` attributes to icon-only buttons.
- **Rule**: Prefer icons for primary table actions (Edit/Delete) to maximize horizontal space and reduce visual clutter. Always provide a `title` attribute for screen readers and tooltips.

### 2026-05-20 - System-Wide Audit and Critical Fixes
- **Action**: Performed full audit using `codebase_investigator`, fixing critical issues.
- **Goal**: Resolve missing database migrations, backend validation flaws, and frontend UX bugs.
- **Outcome**: 
  - Restored missing `create_shifts_table`, `create_sale_items_table`, and `create_categories_table` migrations.
  - Secured `PosController@checkout` with server-side calculation and pre-deduction inventory validation.
  - Fixed mass-assignment vulnerabilities in `Sale` and `Voucher` models.
  - Replaced native `prompt()` in the POS UI with an Alpine.js modal for closing shifts.
- **Rule**: Never trust client-side prices in POS systems. Always recalculate totals server-side based on database records before processing transactions.

### 2026-05-20 - System-Wide Notification Standardized
- **Action**: Centralized all success, error, and validation notifications into the main layouts (`admin`, `staff`, `guest`, `portal`) using SweetAlert2 toasts.
- **Goal**: Eliminate redundant in-page alert blocks and provide a consistent, high-end notification experience.
- **Outcome**: 
  - Standardized success/error/status session handling in `layouts.admin` and `layouts.staff`.
  - Added validation error handling to layouts via SweetAlert2, ensuring users see the first validation error immediately.
  - Replaced native browser `alert()` and `confirm()` calls with stylized SweetAlert2 popups (e.g., in POS checkout and Voucher management).
  - Modernized the Captive Portal (`portal/index.blade.php`) with SweetAlert2 toasts for a more professional guest experience.
- **Rule**: Never use `@if(session('success'))` or `@if($errors->any())` directly in management views if they extend a standard layout. The layout now handles these globally via SweetAlert2 toasts.
- **Rule**: Prefer `window.confirmAction({ ... })` for delete/critical actions to maintain a consistent UI across the application.
