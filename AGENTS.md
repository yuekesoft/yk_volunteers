# Repository Guidelines

## Project Structure & Module Organization
- Core: `site.php` (module site class), `manifest.xml` (WeEngine module meta).
- Controllers: `inc/web/*.inc.php` (admin) and `inc/mobile/*.inc.php` (mobile).
- Views: `template/*.html` and `template/mobile/*.html` (match controller names).
- Data: `install.sql` (tables, may be incomplete), images/icons in project root.

## Build, Test, and Development Commands
- Local run (WeEngine): place this repo under `addons/yk_volunteers/` in a WeEngine instance (PHP 7.1+, MySQL 5.6+).
- Access routes directly for debugging, e.g. `?c=site&a=entry&m=yk_volunteers&do=assignments_list` (mobile/admin variants per `doMobile*`/`doWeb*`).
- Import initial schema using `install.sql` or create tables manually per 开发文档.md.

## Coding Style & Naming Conventions
- PHP 7+ with strict, readable code; 4-space indentation; UTF-8 without BOM.
- Files: admin controllers in `inc/web/xxx.inc.php`, mobile in `inc/mobile/xxx.inc.php`; view names mirror controller names.
- Methods: follow WeEngine entry patterns `doWebXxx`/`doMobileXxx` in `site.php`.
- Database: table names prefixed `yk_volunteers_`; columns use `snake_case`.
- Templates: keep logic minimal; prefer passing prepared data from controllers.

## Testing Guidelines
- No automated tests in repo; validate core flows manually:
  - Admin: volunteers CRUD, slot config, auto-assign, reports export.
  - Mobile: bind WeChat, view schedule, leave/replace, self check-in.
- Use sample URLs with date filters and check DB writes. Log review in `data/logs/*` when available.

## Commit & Pull Request Guidelines
- Use Conventional Commits: `feat:`, `fix:`, `chore:`, `refactor:`, `docs:`, `test:`.
- Keep PRs focused; include description, screenshots of UI changes, and steps to reproduce/verify.
- Reference related issues and note any DB migrations or config keys touched.

## Security & Configuration Tips
- Do not commit secrets (OpenIDs, template IDs). Store via system settings (`sysset`) UI.
- Validate parameters on all `doWeb*`/`doMobile*` entries; use WeEngine permission checks.
- Large imports/exports rely on PHPExcel; test on realistic files and guard timeouts.

## Agent-Specific Instructions
- When adding a feature, create matching controller and template files, and update menus in `manifest.xml` if needed.
- Follow patterns in `inc/web/assignments.inc.php` and `inc/mobile/assignments_list.inc.php` for pagination, filtering, and rendering.
