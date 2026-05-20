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

### 2026-05-20 - Enhanced Agent System Setup
- **Action**: Created `architect-fixer` subagent and `self-improvement` skill.
- **Goal**: Enable super-efficient coding and self-improving capabilities.
- **Outcome**: The agent now has a dedicated workflow for documenting successes and failures, ensuring the project's institutional knowledge grows over time.
- **Tip**: Use `invoke_agent(agent_name="architect-fixer", ...)` for complex backend/frontend bugs.
