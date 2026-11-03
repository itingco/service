# Dashboard Device Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add personal users, approved-device access, audit trails, roles, rate limiting, and security administration to the Laravel Sales Dashboard.

**Architecture:** Store users, device token hashes, and audits in the existing SQLite connection. Replace shared-env-only authentication with database authentication bootstrapped from env, validate approved device cookies on every protected request, and isolate administration behind an admin-role middleware.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, SQLite, PHPUnit.

## Global Constraints

- Existing sales calculations, URLs, filters, and exports remain unchanged.
- Device identity uses a random token; no MAC address or hostname assumptions.
- Passwords use Laravel hashing and secrets are never logged.
- Unknown devices require approval except the initial admin bootstrap device.
- Security tables use `sqlite_targets`.

---

### Task 1: Security persistence and configuration
- [ ] Add security config, migrations, models, password/device token services, and tests.
- [ ] Add installer command for initial admin.

### Task 2: Authentication and device approval flow
- [ ] Add login rate limiting and database password validation.
- [ ] Register unknown devices, auto-approve first admin device, and show pending page.
- [ ] Validate user and approved device on every protected request.

### Task 3: Role and activity enforcement
- [ ] Add role middleware and activity audit middleware.
- [ ] Restrict target/security routes by role.

### Task 4: Security administration UI
- [ ] Add user, device, and audit controllers, requests, routes, and Blade pages.
- [ ] Add navigation and current user/device display.

### Task 5: Deployment, documentation, and verification
- [ ] Update env example and installation guide.
- [ ] Add feature/unit/manual tests and run lint/integrity checks.
- [ ] Build patch and full-project ZIPs without env, vendor, git, or SQLite.
