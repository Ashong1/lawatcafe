# Lawat Cafe Project Instructions

This project is a Laravel-based Point of Sale (POS) and Captive Portal system.

## Architectural Mandates

1. **Backend**: Use Laravel 11+ with Service classes for business logic. Keep Controllers lean.
2. **Frontend**: Use Blade templates with Tailwind CSS. Some components may use React or Vue if specified.
3. **Database**: Use Eloquent models and migrations. Ensure all foreign keys are indexed.
4. **Testing**: Aim for high coverage with Pest or PHPUnit. Always write a test for new features or bug fixes.

## Enhanced Agent Capabilities

We have specialized agents and skills to ensure high-quality delivery:

- **Agent: `architect-fixer`**: Use for complex debugging and architectural changes. It operates with a "Research -> Strategy -> Execution" cycle and focuses on root-cause analysis.
- **Skill: `self-improvement`**: Activated after significant tasks to update project memory and instructions.

## Self-Improvement Loop

Every agent must participate in the self-improvement loop:
1. **Reflect**: After a task, identify what worked and what didn't.
2. **Document**: Add non-obvious learnings to `.gemini/memory/LEARNINGS.md`.
3. **Refine**: Update these project instructions (`GEMINI.md`) if a new standard or pattern is established.

## Common Workflows

- **Bug Fixes**: Reproduce with a test -> Fix -> Validate -> Document in `LEARNINGS.md`.
- **New Features**: Research existing patterns -> Plan in `GEMINI.md` -> Implement -> Test.
