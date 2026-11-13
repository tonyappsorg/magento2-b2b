# Tonyapps_B2b — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

This file is the agreed scope — update it, don't re-litigate it. Merchant-facing description is in README.md.

## Status

- **All six phases built** (foundation, storefront accounts, roles & permissions, hierarchy roll-up, payment
  restrictions, store credit). Review fixes 2026-10-28 and 2026-10-30 applied; email redesign and QA fixes 2026-11-09;
  B2b core e2e (`b2b-company.spec.js` in the workspace `../e2e/tests/` Playwright suite, 6 tests) added 2026-11-10.
- Module: `Tonyapps_B2b` / `tonyapps/module-b2b` / v0.1.0 / proprietary.

### Environment (as developed)

| | |
|---|---|
| Platform | Magento **Open Source** 2.4.8-p4 (`magento/project-community-edition`), DDEV, MariaDB 10.6 |
| PHP | 8.4 runtime; `composer.json` declares `~8.3.0\|\|~8.4.0` (old README said `>=8.3`, matching Tonyapps_ShippingRules) |
| Storefront | Luma (Tonyapps_OneStepCheckout is **disabled** in `app/etc/config.php`) |
| Payment | Adyen, PayPal Braintree (+ Braintree CustomerBalance/GiftCard modules enabled) |
| Other | Riskified_Decider, Riskified_Advice |

No Adobe Commerce B2B modules exist here (`Magento_Company`, `CompanyCredit`, `NegotiableQuote`, `PurchaseOrder`,
`SharedCatalog` are Commerce-only). Everything is built from scratch — so **no upgrade path to Commerce B2B**; the two
would collide if Commerce is ever adopted.

### Phases

1. **Foundation** — ✅ entity + schema, admin grid/form, ACL, customer↔company link, backend assignment (customer
   form + grid mass action), customer-group sync.
2. **Storefront accounts** — ✅ company registration (standalone + inside customer registration), approval workflow
   + transactional emails, company dashboard (profile, users).
3. **Roles & permissions** — ✅ permission catalogue, role CRUD in admin and storefront, enforcement everywhere.
4. **Hierarchy roll-up** — ✅ child company CRUD, role inheritance, consolidated descendant order view.
5. **Payment restrictions** — ✅
6. **Store credit** — ✅

Per-phase "as built" notes and verification counts are under *Change history*.

## Architecture

### Feature → implementation (brief bullets)

| Brief bullet | How |
|---|---|
| Create companies from backend | UI-component listing + form, `Tonyapps_B2b::companies` ACL (ShippingRules was the pattern) |
| Create companies from frontend | `b2b/company/create` + `createPost` → company in `pending`, submitter becomes company admin; always admin approval (never auto-approve); registered/approved/rejected emails |
| Company creation during registration | Layout-injected fieldset on `customer_account_create` (`form.additional.info`) + `customer_register_success` observer (`Observer\CreateCompanyOnRegister`). One observer, one block — no template override |
| Child companies | `parent_id`/`path`; storefront `b2b/child/index` gated by `children_*`; `SettingResolver` walks the path for inherited values, credit always resolves to the root |
| Roles & permissions | Role CRUD in company scope; admin roles grid/form per company (`tonyapps_b2b_role_form`, `permissions` multiselect field — the planned jstree ACL widget was not used), storefront plain nested checkbox list |
| Assign users from backend | `Plugin\Customer\AddCompanyToForm` + `Observer\SaveCustomerCompany` (Company fieldset on customer form), plus customer-grid mass action |
| Restrict payment methods per company | `payment_method_is_active` observer (`Observer\RestrictPaymentMethods`) + `Model\PaymentRestriction` (~30 lines) |
| Store credit | Offline method via `Payment\Model\Method\Adapter` virtual type (`TonyappsB2bStoreCreditFacade`), `Model\StoreCredit` with row lock; admin Credit page; storefront credit page behind `credit_view` |
| Order approval | Optional `Tonyapps_B2bOrderApproval` addon |
| Quick ordering | Optional `Tonyapps_B2bQuickOrder` addon |
| Pricing/promotions by group | Company holds `customer_group_id`; `Model\CustomerGroupSync` syncs members on company save and member add |

### Optional addons (separate modules; summary kept from the old README)

- [`Tonyapps_B2bOrderApproval`](https://github.com/tonyappsorg/magento2-b2b-order-approval) — requires
  `Tonyapps_B2b`, enabled separately under **Stores > Configuration > Tonyapps > B2B Order Approval**. A member whose
  cart base subtotal meets the website threshold requests approval from the cart page; the company administrator
  reviews buyer and cart, approves or rejects. Approval lets the buyer finish normal checkout — it does not place an
  order or authorize payment. Checkout blocked until approval and if the cart changes after approval. Company admins
  order directly. Threshold in website base currency; zero = every non-admin company order needs approval. First
  release: one approval by the direct company administrator; no role-based approvers or multi-step chains; no
  payment data stored in approval records.
- `Tonyapps_B2bQuickOrder` — SKU/qty entry and CSV upload for members; up to 500 lines (1 MB); supported simple or
  virtual products to the regular cart; valid lines kept when others are invalid, errors shown; cart, checkout,
  payment restrictions and order approval stay in force. No configurable products or saved lists in its first
  release (saved lists → `Tonyapps_B2bRequisitionLists`).
- `Tonyapps_B2bRequisitionLists`, `Tonyapps_B2bQuotes` — use the `requisition_lists_*` / `quotes_request`
  permissions declared here.

### Key classes

- `Model\CompanyContext` — current visitor's company, `isEnabled()`, `isAllowed($permission)`.
- `Model\Permissions` — the storefront permission catalogue (constants, labels, `IMPLIES`).
- `Model\PermissionChecker` — the decision (see *Decisions*).
- `Model\SettingResolver` — walks the materialized path for inherited group / payment mode; credit pool → root.
- `Model\CompanyStatus` — every status change (grid or form) goes through it: group re-sync + notification.
- `Model\CompanyRegistration`, `CompanyUserManagement`, `ChildCompanyManagement`, `Membership`, `CompanyEmail`,
  `CompanyRepository`, `RoleRepository`, `Config`.
- `Model\PaymentRestriction` — the resolved allowed-methods decision.
- `Model\StoreCredit` — all balance movements via one private `applyDelta()` (transaction + `SELECT … FOR UPDATE` on
  the root row); `getPoolCompanyId()` resolves to the root.
- `ResourceModel\Company` — `_beforeSave` cycle rejection; `isActivePath()`; never writes `credit_balance`.
- `Controller\AbstractCompanyAction` — storefront base: checks `isEnabled()`, membership, then
  `protected string $requiredPermission` (default `profile_view`; `''` = membership only); denied → redirect to
  `customer/account` (for `profile_view`) or `b2b/company/index`. Subclasses implement `executeForCompany()`.
- `Plugin\Sales\AllowCompanyOrderView` — around core `OrderViewAuthorization`.
- `Block\Company\Link` / `Block\Company\Delimiter` — account nav.

### Storefront (route `b2b`, Luma)

| Path | What | Permission |
|---|---|---|
| `b2b/company/create`, `createPost` | registration (login required; submitter becomes admin; starts Pending) | logged in, not a member |
| `b2b/company/index` | dashboard; "waiting for approval" notice while pending | membership only (also pending/blocked) |
| `b2b/company/edit`, `save` | profile | `profile_edit` |
| `b2b/user/index`, `save`, `delete`, `role` | users list, add/remove, change role | `users_view` / `users_manage` |
| `b2b/role/index`, `save`, `delete` | Roles and Permissions | `roles_view` / `roles_manage` |
| `b2b/child/index`, `save` | Child Companies | `children_view` / `children_manage` |
| `b2b/order/index` | Company Orders (subtree, paginated) | `orders_view_company` |
| `b2b/credit/index` | Store Credit balance + history (read-only) | `credit_view` |

- Account navigation (`view/frontend/layout/customer_account.xml`): "My Company" and its section links are their own
  block of `customer_account_navigation`, between My Account (250) and My Orders (230), delimiter at 245; the other
  B2B modules add links at 235–238. Each link needs its permission; delimiters and links are hidden for non-members.
  The container is `customer_account_navigation` (underscores); hyphenated `customer-account-navigation-*` names are
  its child links.
- Every `b2b_*` layout's content block is `cacheable="false"`.
- `checkout_index_index.xml` — store credit method on Luma checkout.
- `b2b/*` routes redirect a guest to login.

### Admin

- Menu **Customers › Companies** (`tonyapps_b2b/company/index`, resource `Tonyapps_B2b::companies`).
- Company grid: ID, name, email, status, customer group, created; hierarchy path and level available but hidden;
  edit/delete action column; *Roles* and *Credit* links per row; mass actions Approve / Reject / Block / Delete
  (mass delete deepest-first so a whole branch succeeds).
- Company form fieldsets: Company Details, Structure & Pricing (parent company, customer group), Legal Address,
  Payment Methods (mode + multiselect of every store method, inactive ones included on purpose so a list survives a
  method being toggled).
- Roles grid/form per company (`company_id` from the URL; scope kept on reload). A role's company cannot change
  after creation.
- Credit page: balance, add/remove form, full history.
- Customer form: *Company* fieldset (company + role). Customer grid mass actions *Assign to Company* / *Remove from
  Company* (reuse core `AbstractMassAction`, gated by the company ACL).
- Layouts: `tonyapps_b2b_company_{index,edit,new}`, `tonyapps_b2b_role_{index,edit,new}`, `tonyapps_b2b_credit_index`.

### Events

| Event | Observer | Purpose |
|---|---|---|
| `customer_register_success` (frontend) | `CreateCompanyOnRegister` | company from the registration form; failure reported + logged, not thrown |
| `adminhtml_customer_save_after` (adminhtml) | `SaveCustomerCompany` | membership from raw request; save without the fieldset leaves membership alone |
| `sales_order_place_before` | `StampCompanyOnOrder` | stamp `company_id` once, never recomputed, never overwrites |
| `payment_method_is_active` | `RestrictPaymentMethods`, `StoreCreditAvailability` | one `<event>` block, both observers |
| `sales_order_place_after` | `DebitStoreCreditOnOrder` | debit; dispatched before the order row is saved, so throwing on shortfall stops the order |
| `sales_model_service_quote_submit_failure` | `RevertStoreCreditOnFailedOrder` | revert the ledger net for the increment ID unless the order row was saved |
| `sales_order_creditmemo_refund` + `sales_order_creditmemo_save_commit_after` | `RefundStoreCredit` | marks the memo, then moves the ledger after commit, once per refund; logged, not thrown |
| `order_cancel_after` | `RevertStoreCreditOnCancel` | refund by order number, from the ledger, capped at the cancelled total |

`module.xml` sequence: `Magento_Customer`, `Directory`, `Store`, `Backend`, `Ui`, `Config` (the original plan said
after Customer, Sales, Quote, Payment, Checkout — not what was built). `i18n/en_US.csv`.

## Data model (`etc/db_schema.xml` + `db_schema_whitelist.json`)

| Table | Columns / notes |
|---|---|
| `tonyapps_b2b_company` | `entity_id`, `parent_id` (nullable self-FK), `path` + `level` (materialized path, `catalog_category_entity` style — "my root" and "my subtree" in one query, no recursion), `name`, `legal_name`, `vat_tax_id`, `email`, `telephone`, `street`, `city`, `region_id`, `region`, `postcode`, `country_id`, `status` (0 pending / 1 active / 2 rejected / 3 blocked), `customer_group_id` (nullable = inherit), `admin_customer_id`, `payment_mode` (0 inherit / 1 all / 2 restrict-to-list), `credit_balance` (root rows only; non-root ignored), `comment`, `created_at`, `updated_at` |
| `tonyapps_b2b_company_customer` | `customer_id` (PK), `company_id`, `role_id`, `created_at`. Flat table rather than EAV: one join into the customer grid, no attribute overhead. (Plan listed a `status` column; not built.) |
| `tonyapps_b2b_role` | `role_id`, `company_id`, `role_name`, `is_default`, `permissions` (comma-separated codes), `created_at`, `updated_at` |
| `tonyapps_b2b_company_payment` | `company_id`, `method_code` (composite key; a set). Read only when `payment_mode = 2`; cascades with the company |
| `tonyapps_b2b_credit_history` | `entity_id`, `company_id` (always the root), `delta`, `balance_after`, `type` (`adjust\|order\|refund\|revert`), `order_id`, `order_increment_id`, `comment`, `admin_user_id`, `customer_id`, `created_at`. Audit trail; checkout reads the balance on the company row |
| `sales_order`, `sales_order_grid` | + `company_id` (nullable, indexed) |

The planned `tonyapps_b2b_role_permission` table (`permission_id`, `role_id`, `resource_id`, `permission`
allow/deny, mirroring Magento's ACL rule table) was **not built** — see Decisions.

## Configuration and ACL

| Path | Default | Notes |
|---|---|---|
| `tonyapps_b2b/general/enabled` | 1 | master switch (website scope): turns off company pages, registration, every permission check (incl. viewing colleagues' orders), payment rules and the store credit method. Refunds/cancellations of spent credit keep working; admin pages stay |
| `tonyapps_b2b/registration/frontend_enabled` | 1 | allow storefront registration |
| `tonyapps_b2b/registration/in_customer_registration` | 1 | company section on Create an Account (depends on the above) |
| `tonyapps_b2b/email/new_company_template` | `tonyapps_b2b_email_new_company_template` | `company_new.html`, to store General Contact; links to the admin edit page |
| `tonyapps_b2b/email/approved_template` | `tonyapps_b2b_email_approved_template` | `company_approved.html` |
| `tonyapps_b2b/email/rejected_template` | `tonyapps_b2b_email_rejected_template` | `company_rejected.html` |
| `payment/tonyapps_b2b_credit/*` | `active` 1, `model` `TonyappsB2bStoreCreditFacade`, `order_status` pending, `title` Store Credit, `group` offline, `can_use_checkout` 1, `can_use_internal` 1, `sort_order` 5 | admin group *Store Credit (B2B Companies)* under Sales › Payment Methods. No `payment_action`, so no gateway command runs |

- Config section `tonyapps_b2b` in tab `tonyapps`, resource `Tonyapps_B2b::config`.
- There is **no** separate "credit enable" setting (the payment method's *Enabled* is that switch) and **no**
  "auto-refund" setting (credit memos and cancellations always return credit). The original plan listed both.
- Email template IDs are already in core's config-path form, so EmailDesigner designs can be picked in the settings.
  Emails are sent from the default store view (approvals happen in admin, which has no usable frontend config);
  failures are logged, never thrown.
- Email Designer (`etc/di.xml`, `BlockPool`): block `b2b_company_details` (details table, new-company email); starter
  designs in `view/adminhtml/email_designer/company_{new,approved,rejected}.mjml` (the approved/rejected boxes are
  editable text, not blocks).

**Admin ACL** (`etc/acl.xml`): `Magento_Customer::customer` › `Tonyapps_B2b::companies` (Companies) ›
`Tonyapps_B2b::credit` (Adjust Store Credit); `Magento_Config::config` › `Tonyapps_B2b::config`. Assigning a company
on the customer form needs `Tonyapps_B2b::companies`.

**Storefront permissions** (`Model\Permissions`, 14 codes, not in `acl.xml`):

| Code | Implies |
|---|---|
| `profile_view` (always granted) / `profile_edit` | edit → view |
| `users_view` / `users_manage` | manage → view |
| `roles_view` / `roles_manage` | manage → view |
| `children_view` / `children_manage` | manage → view |
| `orders_view_company` | (includes descendants) |
| `credit_view` / `credit_use` | use → view |
| `requisition_lists_view` / `requisition_lists_manage` | manage → view (used by B2bRequisitionLists) |
| `quotes_request` | used by B2bQuotes |

The original plan's tree also had `orders_view_own` (not built; members' own orders stay in core's My Orders) and `place_order`
(narrowed to `credit_use`, see 2026-10-28).

## Decisions and constraints

Owner decisions answered 2026-09-23:
1. **Store credit = full-coverage only.** Offline method, selectable only when the available balance covers the
   whole grand total. No total collector, no split payment with Adyen/Braintree. *Upgrade path: total collector +
   checkout JS — about the same size again as the rest of the credit feature.*
2. **Hierarchy = full roll-up.** A child inherits customer group, payment restrictions and roles unless it overrides
   them; credit is a **single pool owned by the root** that every descendant spends from; parent admins get a
   consolidated view of descendant orders.
3. **Membership = one company per customer**, admin assignment or invite. Single `company_id`; no switching.
4. **Out of scope for core:** order approval and SKU quick ordering are optional addons. Purchase orders and shared
   catalogs out of scope (negotiable quotes and requisition lists later became the B2bQuotes and
   B2bRequisitionLists addons).

Decisions 2026-09-25 (phase 2): **admin approval** (never auto-approve); **reject** a company registration from
someone already in a company (message tells them to ask their company administrator); notifications to the store's
**General Contact**.

Not built because Magento already does it:
- **Group pricing/promotions** — tier prices, catalog and cart price rules are native; the module only assigns a
  group to a company and keeps members in it (brief bullet 8).
- **Invite tokens/emails** — adding a user creates the customer immediately and reuses Magento's "set your password"
  email. *Ceiling: no "invited, not yet accepted" state.*
- **Permission tree UI in admin** — the plan was to reuse the ACL jstree widget; superseded by phase 3 (storefront
  permissions are not admin ACL; storefront form is a plain checkbox list).

Rules that hold the module together:
- **A company confers its group only while Active.** No group → never touches anyone; pending/rejected/blocked →
  members in the default group. Approving applies, blocking removes, re-approving restores. Since 2026-10-28 every
  company on the path must be active (`isActivePath`) for permissions, payment rules, store credit and group.
- **A company that is not Active has no say** over payment (members pay as ordinary customers) and grants no
  permissions — to anyone, including its admin. Its dashboard still opens (membership, not permission).
- **Field whitelists, not blacklists.** Registration, storefront profile save and child save copy only listed fields;
  status, parent, customer group and administrator are never taken from a form. Covered by tests.
- **Permission decision** (`PermissionChecker`), in order: (1) inactive company/path grants nothing; (2) company
  administrator (`admin_customer_id`) may do everything — the one person who could otherwise lock themselves out;
  (3) member's role, falling back to the company default role; (4) no role and no default → company page only.
  Implied permissions are added on save; unknown codes dropped.
- **No self-promotion** (2026-10-30): a member can only assign a role (or leave someone on a default role) whose
  permissions they hold, can only save a role with permissions they hold, can't edit/delete the role they act under
  (own or fallback default), and nobody can change their own role.
- **Roles inherit whole, never merged.** A company with any own role uses only those; one with none borrows the
  nearest ancestor's full set incl. default. Ownership check is "role is on my company's path"; sibling/unrelated
  roles refused in checks and assignment. Cross-company role IDs posted anywhere are refused; stale links grant
  nothing. A role still held by members can't be deleted. One default role per company is enforced in the
  repository on every save.
- **Child companies start Active** (parent already approved; child can't set a group) — a second approval would be
  friction without safety gain.
- **Orders carry `company_id`** stamped at `sales_order_place_before`, never recomputed (deriving from current
  membership would move old orders between companies).
- **Company order view plugin** only widens *whose* orders are visible: returns core's answer when it says yes,
  honours visible-on-front statuses, only accepts the viewer's own subtree.
- **Payment resolver** walks up and stops at the first company that decided: "allow all" ends with no restriction
  (a child can opt out), "restrict" ends with its own list; restrict-with-nothing allows **nothing** (distinct from
  no restriction; the form says so). Inherit is default, so install changes nothing. Customer taken from the quote
  if present (checkout + admin-created orders), else session. Never re-enables a method already turned off.
- **Store credit concurrency**: all movements through `applyDelta()` with `FOR UPDATE`; proven with two PHP
  processes racing for a balance covering one: one succeeded, the other refused, balance correct. Only debits are
  guarded; admin adjustments may go negative on purpose. Availability = company member (active path) **and** root
  balance ≥ grand total **and** `credit_use` (or company admin); admin-created orders aren't affected by
  `credit_use`. Partial balances keep the method hidden rather than failing late.
- **Admin can't overwrite the balance**: the resource model never writes `credit_balance`; only `StoreCredit` moves
  it, with a ledger entry. Admin amounts parsed in the admin's locale ("1,234.50" en, "1.234,50" de).
- **Credit and companies**: a company holding a balance or ledger, or whose subtree paid with store credit, can't be
  deleted or moved to another parent (refunds would hit the wrong pool). Block it instead.
- **Company deletion**: blocked while children exist (re-parent first); members detached, not deleted, released to
  the default group if the company managed one.
- **Cycle safety**: the parent select never offers the company's own subtree, and `ResourceModel\Company::_beforeSave`
  rejects self-parent / descendant-parent on every save path.
- **Group sync rules** (`CustomerGroupSync`): company save re-applies groups across its **whole subtree** (descendants
  with their own group keep it); joining a company that resolves to no group leaves the customer's group alone;
  leaving/moving/deleting takes back only the group the old company gave (hand-set groups kept; moving to a company
  without a group drops the old pricing); clearing a company's group (or moving under a parent without one) sends
  members still in the old group to default. Changes reach logged-in shoppers on their next request (core customer
  notification storage).
- **`ponytail:`** group writes go straight to `customer_entity.group_id` with affected rows re-indexed by hand — a
  repository save per customer would take minutes on a large company; trade-off: `customer_save_*` observers don't
  fire for these changes.
- **`ponytail:`** permissions are a flat constant list in `Model\Permissions`, not `etc/acl.xml` (admin ACL would show
  them to every admin editing an admin role), and roles store a comma-separated column, not a join table (few
  permissions, few roles, nothing asks "which roles grant X"). Presence = allowed, no explicit deny.
- **Enforcement** is `$requiredPermission` on the controller base, not the planned `controller_action_predispatch`
  observer + mapping table: a new page can't be added without deciding what it needs.
- **Store credit method** is a `Magento\Payment\Model\Method\Adapter` virtual type (supported route; core offline
  methods still use deprecated `AbstractMethod`). The Adapter reads `can_use_checkout`/`can_use_internal` itself —
  without them the method is never offered.
- Every permission in the catalogue is enforced by existing code — a toggle that does nothing is worse than none.

## Known limitations and open points

Open questions (status as of the last README):
1. ~~Credit ownership~~ — ✅ one pool per root, shared by descendants; non-company customers have no balance and are
   never offered the method.
2. **Company deletion** — ✅ decided and built (see Decisions).
3. **Multi-website / multi-currency** — assumed: companies global, credit base-currency only. Breaks if websites run
   different base currencies.
4. **Sales representative** — Adobe assigns an admin user per company. Skipped unless wanted.
5. **Order visibility** — assumed separate "Company Orders" page (built that way), not merged into "My Orders".
6. **REST / GraphQL** — no API surface in v1. Needed for a PWA or ERP integration?
7. ~~Registration edge case~~ — ✅ rejected with a message to ask their company administrator.
8. **Can a company admin change their own customer group?** Assumed no — admin-only field. The sync does not *stop*
   an admin editing a member's group by hand on the customer form; it is overwritten the next time that company is
   saved. Say if it should be locked. (The original plan said to block manual per-member edits unless an override
   flag is set — not built.)
9. **Email recipients** — ✅ General Contact gets new-company notifications. Still open for credit adjustments.
10. **Riskified** — assumed store-credit orders still run the normal fraud check; no change to `Riskified_Decider`.
11. **OneStepCheckout** — disabled. If re-enabled, the store-credit method needs its Vue checkout wired up; v1
    targets Luma only.

Other limitations:
- Not built from the plan: company-grid member count; Company column in the customer grid (needs a `customer_grid`
  indexer fieldset extension).
- Hyvä not supported (see WORKPLAN item 5).
- Per WORKPLAN QA log 2026-11-10: child companies created from the storefront can't be deleted there (e2e runs leave
  "E2E Branch" rows; clean with SQL).

Known blockers outside this module (`setup:di:compile` failed for the whole project, so production deployment did too; developer mode doesn't need it; both causes pre-existing):
1. `Tonyapps_ShippingRules/Block/Adminhtml/Rule/Import.php` redeclared inherited `$formKey` as readonly — fatal on
   PHP 8.4. **Fixed** (renamed to `$formKeyProvider`) with the user's agreement.
2. `Tonyapps/NotifyOnNewBrowserLogin` used the `Tony\NotifyOnNewBrowserLogin` namespace. **Fixed 2026-09-30**:
   renamed to `Tonyapps_NotifyOnNewBrowserLogin`, `cbschuld/browser.php` added to root composer.json. Still disabled.

Earlier verification gaps (phases 1–2): admin screens not clicked through in a browser because the DDEV router could
not bind port 443; covered later by the 2026-11-10 e2e and manual QA.

## Tests

```sh
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2b/Test/Unit
```

- Unit coverage targets real logic: inheritance resolver, group sync, permission checker, credit debit/refund math
  (incl. the concurrency guard), payment restriction, role inheritance, layout schema. Nothing else gets a test unless
  it earns one. 141 unit tests green as of 2026-11-10 (per WORKPLAN).
- No `Test/Integration` in this module.
- E2E: workspace Playwright suite `../e2e/tests/` (`b2b-company.spec.js`) (registration → pending → approval/rejection emails, roles + users incl.
  set-password and permission enforcement, child company, company orders, payment restrictions).
- Email screenshots live in `docs/emails/` (export-ignored from the Composer package via `.gitattributes`).

## Change history

### 2026-11-13 Email Designer blocks and starter designs
Registered the new-company details table as an Email Designer block and a starter design for each of the three
company emails, a copy of the built-in template. No dependency on `Tonyapps_EmailDesigner`.

### 2026-11-13 package metadata
`composer.json` requires eav, email, payment, sales and theme, which the code uses. `module.xml` sequences
`Magento_Sales` (the module redefines Sales' order-grid virtual type) and `Magento_Payment`. Quote and checkout are
only reached through events, so they are not required.

### 2026-11-09 QA fix
- Storefront **Roles and Permissions** showed "Nothing beyond seeing the company" for every role: `array_filter()`
  passed permission labels instead of codes. Fixed; admin role grid was not affected.
- **Remove** (user) and **Delete** (role) confirm first (core `data-post` confirm modal).
- Role list **Users** count includes members without their own role on the default role (company administrator left
  out).
- Company page shows the country name ("United States") instead of the code.
- Edit button in the role list no longer shows Luma's action separator.

### 2026-11-09 email redesign
Company emails (new registration to the store, approved, rejected) use the store's email frame with a heading, a
details table or highlighted box, and a button, like other Tonyapps emails. Variables unchanged. Screenshots taken
(Luma, 2026-11-09) in `docs/emails/`.

### Review fixes 2026-10-30
- Company pages never full-page cached: content block of every `b2b_*` page `cacheable="false"`.
- Store Credit offered again: `can_use_checkout` / `can_use_internal` set (Adapter treated missing as off).
- Failed order submit returns the debit (`sales_model_service_quote_submit_failure`): ledger net for the increment ID
  reverted unless the order row was saved; retry reusing the increment ID is debited once.
- No self-promotion (see Decisions).
- Clearing a company's group (or moving under a parent without one) sends members still in the old group to default;
  hand-set groups kept.
- Pending and blocked companies can open their dashboard (membership, not a permission).
- Credit memo refunds move the ledger after commit (`sales_order_creditmemo_save_commit_after`, once per refund).
- Admin: roles grid keeps `company_id` scope on reload; assigning a company on the customer form needs
  `Tonyapps_B2b::companies`; "new company" email links to the admin edit page.
- Fewer queries: Company Orders loads only the subtree's company names; role member counts are one query; users list
  loads once per render.

### Review fixes 2026-10-28
- Store credit refunded on cancel (by order number; `order_id` may be empty when the debit runs), capped at the
  order's cancelled total so a partly invoiced order keeps paying for what was invoiced.
- Admin company form can't overwrite the balance.
- Blocked/pending company switches off its whole subtree (`ResourceModel\Company::isActivePath`).
- Leaving/moving company takes back only the group the old company gave; group changes reach logged-in shoppers next
  request.
- Company Orders lists by storefront-visible statuses like core order history, so store-credit orders (state `new`)
  show.
- Adding users: an email that already has an account is refused on the storefront (that person never agreed to
  join); an administrator can still assign them. Nobody can change their own role.
- `credit_use` ("Pay with the company's store credit") replaces planned `place_order`, narrowed to shared money:
  members still check out with their own card. Only company admin and roles with it can pay with store credit;
  existing roles need it ticked. Admin-created orders unaffected.
- Master switch `tonyapps_b2b/general/enabled` semantics (see Configuration).
- Credit and companies: no delete/move with balance, ledger or credit-paid subtree.
- Admin credit adjustment needs `Tonyapps_B2b::credit`; amounts read in the admin's locale.
- Plan corrections: no "credit enable" or "auto-refund" settings.

### Phase 6, as built (store credit)
- One pool per root; storefront and admin screens say when a company spends someone else's pool.
- Debit on `sales_order_place_after`; refund on credit memo (logged, not thrown — memo already exists); cancel takes
  the amount from the ledger, not the order total, so partial refund then cancel returns only what's outstanding and
  a second cancel returns nothing.
- Admin *Credit* link per company row (balance, add/remove accepting `1234.56` and `1 234,56`, history); storefront
  read-only page behind `credit_view`. Ten permissions at that point, all enforced.
- Verified on DDEV — 113 checks: 81 unit tests (12 new: row lock, refusal without writing, exact-balance spend,
  negative adjustment allowed, partial-refund-then-cancel, double-cancel no-op); 28 functional checks incl. the
  two-process race, pool resolving to root, ledger recording buyer and root, availability for member/non-member/
  guest/blocked; method instantiates via Magento's payment helper and appears among 184 methods; admin page renders;
  39 XML files validate; routes behave.
- XML slip caught by validation: two `<event name="payment_method_is_active">` blocks in one file; merged.

### Phase 5, as built (payment restrictions)
- Three modes: inherit / allow every method / allow only selected. Resolver + `PaymentRestriction` service +
  ~30-line observer. Admin *Payment Methods* fieldset; allowed list in its own table, cascades on delete.
- Verified on DDEV — 88 checks: 69 unit tests (14 new: resolver walk, every branch of the restriction service);
  19 functional (nothing restricted until configured, parent list rolling down, child opt-out, child override,
  restrict-to-nothing, blocked company losing its say, observer hiding exactly the right methods and leaving
  already-unavailable ones, list cascading on delete); admin form and both grids build; method source listed all 183
  store methods.
- One assertion expected insertion order from a set under a composite key; test now compares as a set.

### Phase 4, as built (hierarchy)
- Child companies created/edited by the parent's people at `b2b/child/index`; permissions `children_view`,
  `children_manage`, `orders_view_company` added. Children start Active. Roles inherit whole. Orders stamped with
  `company_id` (added to `sales_order` and `sales_order_grid`). Company Orders (`b2b/order/index`) lists the subtree,
  paginated, each row naming its company; `OrderViewAuthorization` plugin lets members open colleagues' orders.
- Verified on DDEV — 93 checks: 55 unit tests (11 new: role inheritance up the path, usable role); 38 functional and
  render checks (child creation + path cascade, foreign edits refused, group rolling down, roles inherited then
  overridden, ancestor role accepted / unrelated refused, stamping observer for member/guest/non-member/no overwrite,
  subtree order roll-up, pages/links shown only to whoever may use them); 36 XML files validate; `b2b/*` redirect
  guests; both admin grids 200.
- One test passed for the wrong reason (indexed an empty array and caught the PHP error as the refusal); rewritten to
  create a real role in another company.

### Phase 3, as built (roles & permissions)
- Six permissions at first (`profile_*`, `users_*`, `roles_*`); later phases added theirs with their features.
- Not in `acl.xml`; no `role_permission` join table (see Decisions). Enforcement via `$requiredPermission`.
- Admin roles grid/form per company via *Roles* link on the company grid.
- Verified on DDEV — 82 checks: 48 unit tests; 34 functional and render checks (role CRUD, exclusive default role,
  enforcement per role, blocked-company lockout, cross-company refusal, in-use role protection, storefront showing
  exactly the allowed links/forms); admin UI components build and `prepare()`; roles grid scopes to URL company;
  admin company and role grids return 200.
- Bug found and fixed: "one default role per company" lived in the resource model's `_afterSave`, which
  `AbstractDb::save()` skips when nothing changed. Re-marking an already-default role cleared the others' flags
  without setting its own → no default, members dropped to view-only. Rule moved to the repository and forces stored
  state on every save; two unit tests pin it.

### Phase 2, as built (storefront accounts)
- Routes `b2b/company/create|index|edit|save`, `b2b/user/index|save|delete`; "My Company" nav only for members;
  optional company section on Create an Account.
- Access control was deliberately crude until phase 3: company admin manages, others view; `CompanyContext` + one
  `requiresCompanyAdmin` flag on the controller base (replaced by `$requiredPermission` in phase 3).
- Admin Approve/Reject/Block/Delete mass actions; all status changes via `Model\CompanyStatus`.
- Config section added: master enable, storefront registration, offer during customer registration, three email
  templates.
- Verified on DDEV — 75 checks: 22 unit tests; 30 functional (registration ignores privileged fields, pending confers
  nothing, approval applies group, blocking removes it, one company per customer, user add/remove incl. absorbing an
  existing customer and refusing to remove the administrator); 23 render checks with a real logged-in session
  (dashboard pending/approved, forms prefilled and posting correctly, user list, nav link only for members); all XML
  validates; `b2b/*` redirects guests to login. Not clicked through in a browser (port 443), `setup:di:compile` still
  blocked at the time.

### Phase 1, as built (foundation)
- Installed with `bin/magento module:enable Tonyapps_B2b && bin/magento setup:upgrade`; both tables created as
  declared; whitelist matches `setup:db-declaration:generate-whitelist`.
- Verified: 18 unit tests; 28 functional checks against the live DB (path building, subtree re-parenting, cycle
  rejection, group inheritance and override, membership sync, delete guards); 12 wiring checks (grid and form data
  providers, customer-form plugin, save observer incl. save without the fieldset); all four UI components build and
  `prepare()` in adminhtml; all 15 XML files validate against XSDs. An earlier note claimed the admin route was
  verified by a 302 — that was only HTTP→HTTPS; properly checked in phase 3 (grids return 200).
- `AddCompanyToForm` reads membership into the form because the customer data provider has no modifier pool.
- Changed against the plan: no `config.xml`/`system.xml` in phase 1 (arrived in phase 2); schema carried phase-1
  columns only (payment, credit, role tables/columns landed with their phases); no company-grid mass actions until
  the phase-2 approval queue; no member count / customer-grid Company column; `SettingResolver` built early because
  group inheritance needed it.
