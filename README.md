# Tonyapps_B2b — B2B Company Account

Turns standard Magento customer accounts into company accounts: multiple users, roles and
permissions, company hierarchy, per-company payment restrictions, store credit, and
group-based pricing.

**Status: all six phases built** (see *Phases* below). This file is the agreed scope — update it, don't re-litigate it.

## Environment

| | |
|---|---|
| Platform | Magento **Open Source** 2.4.8-p4 (`magento/project-community-edition`) |
| PHP | 8.4 runtime; module declares `>=8.3` (matches Tonyapps_ShippingRules) |
| Module | `Tonyapps_B2b` / `tonyapps/module-b2b` / v0.1.0 / proprietary |
| Storefront | Luma (Tonyapps_OneStepCheckout is **disabled** in `app/etc/config.php`) |
| Payment | Adyen, PayPal Braintree (+ Braintree CustomerBalance/GiftCard modules enabled) |
| Other | Riskified_Decider, Riskified_Advice |

No Adobe Commerce B2B modules exist here (`Magento_Company`, `CompanyCredit`, `NegotiableQuote`,
`PurchaseOrder`, `SharedCatalog` are Commerce-only). Everything below is built from scratch —
which also means **no upgrade path to Commerce B2B**; the two would collide if Commerce is ever
adopted.

## Decisions (answered 2026-09-23)

1. **Store credit = full-coverage only.** Plain offline payment method, selectable only when
   the available balance covers the entire grand total. No total collector, no split payment
   with Adyen/Braintree. *Upgrade path if split payment is ever needed: add a total collector +
   checkout JS — that is roughly the same size again as the rest of the credit feature.*
2. **Hierarchy = full roll-up.** A child inherits customer group, payment restrictions and roles
   from its parent unless it overrides them; credit is a **single pool owned by the root company**
   that every descendant spends from; parent-company admins get a consolidated view of descendant
   orders.
3. **Membership = one company per customer, admin assignment or invite.** A single `company_id`
   per customer. No multi-company switching.
4. **Out of scope for the core module:** order approval and SKU quick ordering are optional
   separate addons. Purchase orders, negotiable quotes, saved requisition lists and shared catalogs
   remain out of scope.

## Optional addon: order approval

[`Tonyapps_B2bOrderApproval`](https://github.com/tonyappsorg/magento2-b2b-order-approval) adds a
checkout approval workflow without making it mandatory for every B2B installation. It requires
`Tonyapps_B2b` and is enabled separately under **Stores > Configuration > Tonyapps > B2B Order
Approval**.

When enabled, a company member whose cart base subtotal meets the configured website threshold
requests approval from the cart page. The company administrator can review the buyer and cart
contents, then approve or reject the request. Approval lets the buyer return to the cart and
complete normal checkout; it does not place an order or authorize payment. Checkout is blocked
until approval and if the cart contents change after approval. Company administrators can place
orders directly. The threshold is in website base currency; zero means every non-admin company
order requires approval.

The first release supports one approval by the direct company administrator. Role-based approvers
and multi-step approval chains are not included. The addon does not store payment data in its
approval records.

## Optional addon: quick order

`Tonyapps_B2bQuickOrder` adds SKU/quantity entry and CSV upload for company members on Magento
Open Source. It requires `Tonyapps_B2b`. Buyers can submit up to 500 lines (1 MB maximum) and add
supported simple or virtual products to the regular cart. Valid lines are retained when other rows
are invalid, with errors shown for the rejected lines. The existing cart and checkout remain in
use, including payment restrictions and order approval. Configurable products and saved company
lists are not included in the first release.

## What we are NOT building because Magento already does it

- **Pricing and promotions per customer group** — tier prices, catalog price rules and cart price
  rules are native. The only thing this module adds is *assigning a customer group to a company
  and keeping its members in that group*. Bullet 8 of the brief is that one sync, nothing more.
- **Invite tokens/emails** — adding a user creates the customer immediately and reuses Magento's
  native "set your password" email. *Ceiling: there is no "invited, not yet accepted" state; the
  user row exists from the moment the company admin adds them.*
- **Permission tree UI in admin** — reuse the ACL resource tree (`etc/acl.xml` subtree) and the
  existing jstree role widget instead of a bespoke tree.

## Data model (`etc/db_schema.xml`)

**`tonyapps_b2b_company`**
`entity_id`, `parent_id` (nullable self-FK), `path` + `level` (materialized path,
`catalog_category_entity` style — gives "my root" and "my subtree" in one query, no recursion),
`name`, `legal_name`, `vat_tax_id`, `email`, `telephone`, legal address
(`street/city/region_id/region/postcode/country_id`), `status`
(0 pending / 1 active / 2 rejected / 3 blocked), `customer_group_id` (nullable = inherit),
`admin_customer_id`, `payment_mode` (0 inherit / 1 all / 2 restrict-to-list),
`credit_balance` (root rows only), `comment`, `created_at`, `updated_at`.

**`tonyapps_b2b_company_customer`** — `customer_id` (PK), `company_id`, `role_id`, `status`.
Flat table rather than EAV attributes: one join into the customer grid, no attribute overhead.

**`tonyapps_b2b_role`** — `role_id`, `company_id`, `role_name`, `is_default`.

**`tonyapps_b2b_role_permission`** — `permission_id`, `role_id`, `resource_id`, `permission`
(`allow`/`deny`). Mirrors Magento's ACL rule table so the resource tree and its widgets are reusable.

**`tonyapps_b2b_company_payment`** — `company_id`, `method_code`. Only read when
`payment_mode = 2`; otherwise resolved up the path.

**`tonyapps_b2b_credit_history`** — `entity_id`, `company_id` (always the root), `delta`,
`balance_after`, `type` (`adjust|order|refund|revert`), `order_id`, `comment`, `admin_user_id`,
`customer_id`, `created_at`. Balance on the company row is the value checkout reads; this table is
the audit trail.

Plus `etc/db_schema_whitelist.json`.

## Storefront permission resources (declared in `etc/acl.xml` under a `Tonyapps_B2b::company` root)

```
company
├── profile_view / profile_edit
├── users_view / users_manage
├── roles_view / roles_manage
├── children_view / children_manage
├── orders_view_own / orders_view_company   (company_* includes descendants)
├── credit_view
├── requisition_lists_view / requisition_lists_manage
└── place_order                              (a view-only role cannot check out)
```

Enforcement: one `PermissionChecker` service, called from a `controller_action_predispatch`
observer mapping our controllers → required resource, from blocks for menu visibility, and from a
quote/payment guard for `place_order`.

## Feature → implementation

| Brief bullet | How |
|---|---|
| Create companies from backend | UI-component listing + form, `Tonyapps_B2b::companies` ACL (ShippingRules is the pattern to copy) |
| Create companies from frontend | `b2b/company/create` controller + Luma form → company in `pending`, submitter becomes company admin; config decides auto-approve vs admin approval; registered/approved/rejected emails |
| Company creation during registration | Layout-injected fieldset on `customer_account_create` + `customer_register_success` observer. One observer, one block — no template override |
| Child companies | `parent_id`/`path`; storefront "Company Structure" page gated by `children_manage`; `SettingResolver` walks the path for inherited values, credit always resolves to the root |
| Roles & permissions (admin + storefront) | Role CRUD in company scope; admin uses the jstree ACL widget, storefront a plain nested checkbox list |
| Assign users from backend | `customer_form` UI modifier adding a Company fieldset (company + role) with a save handler, plus a customer-grid mass action |
| Restrict payment methods per company | `payment_method_is_active` observer against the resolved allowed list. ~30 lines — the whole feature |
| Store credit | Offline method via `Payment\Model\Method\Adapter` (a di.xml virtual type, not the deprecated `AbstractMethod`); availability = member of a company **and** root balance ≥ grand total; debit on order place inside a transaction with `SELECT … FOR UPDATE` on the root row (concurrent checkouts must not double-spend); credit back on credit memo and on cancel; admin "Credit" tab (balance, adjust, history) and a storefront credit page behind `credit_view` |
| Order approval | Optional `Tonyapps_B2bOrderApproval` addon; members submit qualifying carts for company-admin approval before completing checkout |
| Quick ordering | Optional `Tonyapps_B2bQuickOrder` addon; bulk SKU and quantity entry or CSV upload adds supported products to the regular cart |
| Pricing/promotions by group | Company holds `customer_group_id`; sync members on company save and on member add; block manual per-member group edits unless an override flag is set |

Cross-cutting: `etc/config.xml` + `system.xml` section `tonyapps_b2b` (master enable, frontend
registration on/off, auto-approve, registration-form toggle, credit enable + auto-refund
behaviour), `i18n/en_US.csv`, `module.xml` sequenced after `Magento_Customer`, `Sales`, `Quote`,
`Payment`, `Checkout`.

## Phases

1. **Foundation** — ✅ built. Entity + schema, admin grid/form, ACL, customer↔company link,
   backend assignment (customer form + grid mass action), customer-group sync. See
   *Phase 1, as built* below for what changed against this plan.
2. **Storefront accounts** — ✅ built. Company registration (standalone + inside customer
   registration), approval workflow + transactional emails, company dashboard (profile, users).
   See *Phase 2, as built* below.
3. **Roles & permissions** — ✅ built. Permission catalogue, role CRUD in admin and storefront,
   enforcement everywhere. See *Phase 3, as built* below.
4. **Hierarchy roll-up** — ✅ built. Child company CRUD, role inheritance, consolidated descendant
   order view. See *Phase 4, as built* below.
5. **Payment restrictions** — ✅ built. See *Phase 5, as built* below.
6. **Store credit** — ✅ built. See *Phase 6, as built* below.

Tests: unit coverage for the things with real logic — the inheritance resolver, the group sync,
the permission checker, and credit debit/refund math (including the concurrency guard). Nothing
else gets a test unless it earns one.

## Phase 1, as built

Installed and verified on the ddev stack (Magento 2.4.8-p4, PHP 8.4, MariaDB 10.6):
`bin/magento module:enable Tonyapps_B2b && bin/magento setup:upgrade`. Both tables created as
declared; the whitelist matches what `setup:db-declaration:generate-whitelist` generates.

Verified:
- 18 unit tests (`vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2b/Test/Unit`).
- 28 functional checks against the live DB — path building, subtree re-parenting, cycle
  rejection, group inheritance and override, membership sync, delete guards.
- 12 wiring checks — grid and form data providers, the customer-form plugin, and the save
  observer (including that a save without the fieldset leaves membership alone).
- All four UI components build and `prepare()` in the adminhtml area; all 15 XML files validate
  against Magento's own XSDs. (An earlier note claimed the admin route was verified by a 302 —
  that 302 was only the store's HTTP→HTTPS redirect. Properly checked in phase 3: the admin
  company and role grids both return 200.)

Not verified: the rendered admin screens in a browser. The ddev router could not bind port 443
(something else holds it), so nothing was clicked through. Also `setup:di:compile` cannot run in
this project — `Tonyapps_ShippingRules`'s `Block\Adminhtml\Rule\Import` redeclares the inherited
`$formKey` as readonly, which is fatal on PHP 8.4. That is a pre-existing bug in another module,
unrelated to this one and left untouched; developer mode does not need the compile, but
production deployment will until it is fixed.

**Admin → Customers → Companies**: grid (ID, name, email, status, customer group, created;
hierarchy path and level available but hidden) with an edit/delete action column, and a form in
three fieldsets — Company Details, Structure & Pricing (parent company, customer group), Legal
Address.

**Assigning users**, both ways the brief asked for:
- A *Company* fieldset on the admin customer form. Membership is not a customer attribute, so the
  native save does not carry it: `Plugin\Customer\AddCompanyToForm` reads the value into the form
  (the customer data provider has no modifier pool) and `Observer\SaveCustomerCompany` writes it
  from the raw request on `adminhtml_customer_save_after`.
- A *Assign to Company* mass action on the customer grid, with a "Remove from Company" entry.
  Reuses Magento's `AbstractMassAction` for the grid-filter plumbing, gated by the company ACL.

**Group sync rules** (`Model\CustomerGroupSync`) — the whole of bullet 8, since tier prices and
price rules are already group-aware:
- Saving a company re-applies groups across its **whole subtree**, because both the group and the
  parent can change what descendants resolve to. Descendants that set their own group keep it.
- Joining a company that resolves to **no** group leaves the customer's group untouched — only a
  company that actually manages a group gets to move people.
- Leaving a company, or the company being deleted, puts members back in the default group — but
  only if that company managed a group in the first place.
- Writes go straight to `customer_entity.group_id` with the affected rows re-indexed by hand.
  Marked with a `ponytail:` comment: a repository save per customer would take minutes on a large
  company, and the trade is that `customer_save_*` observers do not fire for these changes.

**Cycle safety**: the parent select never offers the company's own subtree, and
`ResourceModel\Company::_beforeSave` rejects a cycle anyway (self-parent, or a parent that is one
of your own descendants) so it holds for every save path, not just the form.

**Changed against the plan above**
- No `etc/config.xml` / `system.xml` yet — phase 1 has nothing to toggle. The `enabled` flag
  arrives with the first feature that branches on it (phase 2).
- Schema carries phase-1 columns only. `payment_mode`, `credit_balance`, `role_id` and the role,
  permission, payment and credit tables land with their own phases; declarative schema makes
  adding them a non-event.
- No mass actions on the *company* grid. Bulk approve belongs with the phase-2 approval queue.
- No member count in the company grid, and no Company column in the customer grid — the latter
  needs a `customer_grid` indexer fieldset extension and nothing in the brief asks for it.
- `SettingResolver` exists already (phase 4 in the plan) because group inheritance needs it now.
  It resolves the customer group today; payment restrictions and credit join the same path walk.

## Phase 2, as built

Decisions taken 2026-09-25: **admin approval** (never auto-approve), **reject** a company
registration from someone who already belongs to one, notifications to the **store's General
Contact**.

**The rule that holds it together**: a company confers its resolved customer group on its members
*only while it is Active*. A company that resolves to no group never touches anyone; a company
that would resolve to one but is pending, rejected or blocked puts its members in the default
group. Without this a self-registered company would hand itself wholesale pricing the moment it
was submitted. Approving applies the group, blocking takes it away, re-approving restores it.

**Storefront** (route `b2b`, Luma):
- `b2b/company/create` — registration form; requires login, because the submitter becomes the
  company administrator. Companies always start Pending.
- `b2b/company/index` — dashboard, visible to every member; shows a "waiting for approval" notice
  while pending.
- `b2b/company/edit|save` — profile, company administrator only.
- `b2b/user/index|save|delete` — user list, add and remove; company administrator only.
- "My Company" appears in the account navigation only for members, so a normal shopper's account
  menu is untouched.
- An optional company section on Create an Account (`form.additional.info` container), gated by
  config; `customer_register_success` creates the company afterwards, and a failure there reports
  and logs rather than throwing — the account already exists and the customer is already logged in.

**Access control, deliberately crude until phase 3**: the company administrator
(`admin_customer_id`) manages; everyone else can only view the dashboard. `CompanyContext` and one
`requiresCompanyAdmin` flag on the controller base carry it. Phase 3 replaces that flag with the
role and permission check; nothing here pretends to be finer-grained than it is.

**Field whitelists, not blacklists**: both the registration service and the storefront profile
save copy only from an explicit list of fields. Status, parent company, customer group and the
administrator are never taken from a form — posting them is ignored, so a company cannot approve
itself, move into another company's tree, or pick its own pricing group. Covered by tests.

**Admin**: Approve / Reject / Block / Delete mass actions on the company grid. Every status change
— grid or form — goes through `Model\CompanyStatus`, so the group re-sync and the notification can
never be remembered by one caller and forgotten by another. Mass delete works deepest-first so
selecting a whole branch succeeds. Emails are sent from the default store view (approvals happen
in the admin area, which has no usable frontend config) and failures are logged, never thrown: a
dead mail server must not roll back an approval.

**Config** (`Stores > Configuration > Tonyapps > B2B Company Account`): master enable, allow
storefront registration, offer it during customer registration, and the three email templates.

**Verified on ddev** — 75 checks, all passing:
- 22 unit tests.
- 30 functional checks: registration ignores privileged fields, pending confers nothing, approval
  applies the group, blocking removes it, one-company-per-customer, user add/remove including
  absorbing an existing customer and refusing to remove the administrator.
- 23 render checks: every storefront template rendered with a real logged-in session — dashboard
  pending and approved, both forms prefilled and posting to the right action, user list, and the
  account-nav link appearing only for members.
- All XML validates against Magento's XSDs; `b2b/*` routes redirect a guest to login.

**Still not verified**: nothing clicked through in a browser (the ddev router cannot bind port 443
on this machine), and `setup:di:compile` still cannot run — see below.

## Phase 3, as built

**Permissions are a flat list of six**, not a tree, and every one of them is enforced by code that
exists today:

```
profile_view / profile_edit      users_view / users_manage      roles_view / roles_manage
```

Later phases bring their own — `children_*` with the hierarchy pages, `orders_*` with the
consolidated order view, `credit_view` and `place_order` with store credit. A toggle that does
nothing is worse than no toggle.

**Two deliberate departures from the plan above**, both to avoid machinery the shape of the
problem does not need:
- *Not* declared in `etc/acl.xml`. Magento's ACL is the **admin** permission tree; storefront
  company permissions placed there would appear to every administrator editing an admin role,
  which is not what they are. `Model\Permissions` holds the catalogue instead.
- *Not* a `role_permission` join table. A role stores a comma-separated list in one column. There
  are six permissions and a handful of roles per company, and nothing ever asks "which roles grant
  X". Presence means allowed, absence means denied; there is no explicit deny.

**How a decision is made** (`Model\PermissionChecker`), in order:
1. A company that is not Active grants nothing — to anyone, including its administrator.
2. The company administrator may do everything; they are the one person who could otherwise lock
   themselves out by editing a role.
3. Otherwise the member's own role decides, falling back to the company's default role.
4. With no role and no default, a member can still see the company page and nothing else.

Permissions that depend on another pull it in automatically — saving "edit the profile" without
"view the profile" would make a role that cannot reach the page it may change. Unknown permission
names are dropped on the way in, wherever they came from.

**Enforcement** is a `$requiredPermission` property on the storefront controller base rather than
the planned `controller_action_predispatch` observer and its mapping table: a new page cannot be
added without deciding what it needs, and the requirement sits where the page is. The dashboard,
user list and role pages all show only what the visitor may actually use.

**Cross-company safety**: a role belongs to exactly one company. Posting another company's role ID
— when saving a role, adding a user, or changing someone's role — is refused, and a stale link to
a role from elsewhere grants nothing. A role still held by members cannot be deleted.

**Admin**: roles grid and form per company, reached from a *Roles* link on each row of the company
grid; the grid scopes itself to `company_id` from the URL. A role's company cannot be changed after
creation — moving a role between companies would silently change what its holders can do.

**Verified on ddev** — 82 checks, all passing:
- 48 unit tests (including the regression below).
- 34 functional and render checks: role CRUD, exclusive default role, permission enforcement per
  role, blocked-company lockout, cross-company refusal, in-use role protection, and the storefront
  showing a member exactly the links and forms their role allows.
- All admin UI components build and `prepare()`; the roles grid scopes to the URL's company; the
  admin company and role grids both return 200.

**One real bug found and fixed while testing.** The "one default role per company" rule lived in
the resource model's `_afterSave`, which `AbstractDb::save()` never reaches when the model reports
no data changes. Re-marking an already-flagged role as default therefore cleared the other roles'
flags without setting its own, leaving the company with **no** default at all — and every member
who relied on it silently dropped to view-only. The rule now lives in the repository and forces
the stored state on every save, whatever `save()` did. Two unit tests pin it.

## Phase 4, as built

**Child companies** are created and edited by the parent's own people, from
`b2b/child/index`. Three permissions arrive with the features that need them: `children_view`,
`children_manage`, `orders_view_company`.

A child starts **Active**, not Pending. Its parent is already approved, and a storefront user
cannot set a customer group — the child inherits its parent's — so a new branch grants nobody
anything they did not already have. A second approval would be friction without a safety gain.
The same whitelist rule as everywhere else applies: status, customer group, parent and
administrator are never taken from the form.

**Roles inherit whole, never merged.** A company that defines any role of its own uses only those;
one that defines none borrows the nearest ancestor's entire set, default included. Merging two
companies' lists would produce role names nobody created and a default nobody chose. A member of a
child company can therefore hold a role row that belongs to the parent — so the ownership check
became "is this role on my company's path", not "is it mine". A role from a sibling or an
unrelated company is still refused, in the permission check and when assigning.

**Orders carry `company_id`**, stamped once at `sales_order_place_before` and never recomputed.
Deriving it later from the buyer's current membership would drag their old orders into a new
company's view and lose them from the old one's history. The column is added to `sales_order` and
carried into `sales_order_grid` so the admin grid can use it.

*Company Orders* (`b2b/order/index`) lists the whole subtree, paginated, each row naming its
company. Opening a colleague's order needs one plugin on Magento's `OrderViewAuthorization`:
without it every row belonging to someone else would 404 and the page would be a dead end. The
plugin only widens *whose* orders are visible — it returns the core answer untouched when that
already says yes, still honours the visible-on-front statuses, and only accepts orders from the
viewer's own subtree.

**Verified on ddev** — 93 checks, all passing:
- 55 unit tests (11 new, covering role inheritance up the path and what counts as a usable role).
- 38 functional and render checks: child creation and path cascade, foreign-company edits refused,
  group rolling down to descendants, roles inherited then overridden, an ancestor's role accepted
  and an unrelated company's refused, the stamping observer (member, guest, non-member, and not
  overwriting an existing stamp), subtree order roll-up, and the storefront pages showing each
  page and link only to whoever may use it.
- All 36 XML files validate; every `b2b/*` route redirects a guest to login; both admin grids 200.

One test in the first run passed for the wrong reason — it indexed an empty array and caught the
resulting PHP error as if it were the refusal under test. Rewritten to create a real role in
another company and assert the refusal properly.

## Phase 5, as built

Three modes per company: **inherit** (keep looking up the hierarchy), **allow every method**, and
**allow only the selected methods**. Inherit is the default, so installing this changes nothing
until somebody configures a company.

The resolver walks the path from the company upwards and stops at the first company that has
actually decided: "allow every method" ends the walk with no restriction — so a child can opt out
of a parent's restriction — and "restrict" ends it with that company's own list. A company set to
restrict with nothing selected allows **nothing**; that is an empty list, deliberately distinct
from "no restriction", and the form says so.

Enforcement is one observer on `payment_method_is_active`, about thirty lines, plus a
`PaymentRestriction` service holding the actual decision so the same question can be asked outside
an event later. The observer takes the customer from the quote where there is one — which covers
both checkout and an order an administrator places for a customer — and from the session
otherwise. It never re-enables a method something else already turned off.

As everywhere else in this module, **a company that is not Active has no say**: its people pay as
ordinary customers rather than being locked out of checkout entirely.

Admin: a *Payment Methods* fieldset on the company form — the mode, and a multiselect of every
method the store has. Inactive methods are listed on purpose, so a company's list survives a
method being switched off and on again. The allowed list lives in its own table and cascades away
with the company.

**Verified on ddev** — 88 checks, all passing:
- 69 unit tests (14 new: resolver walk, and every branch of the restriction service).
- 19 functional checks: nothing restricted until configured, a parent's list rolling down, a child
  opting out, a child overriding with its own list, restrict-to-nothing, a blocked company losing
  its say, the observer hiding exactly the right methods and leaving already-unavailable ones
  alone, and the list cascading away on delete.
- Admin form and both grids build; the method source lists all 183 of this store's methods.

One assertion failed first time because it expected insertion order from a set stored under a
composite key. The list is a set; the test now compares it as one.

## Phase 6, as built

**One pool per root company.** Every descendant spends from the same balance — that is what "full
roll-up" means — so the balance column on a non-root row is ignored and `getPoolCompanyId()`
resolves to the root. The storefront and admin screens both say so when a company is spending
someone else's pool.

**The concurrency guard is the point of the class.** Every movement goes through one private
`applyDelta()` that opens a transaction and reads the balance `FOR UPDATE` before deciding. Two
checkouts finishing together would otherwise both read the old balance, both decide they could
afford it, and spend the same money twice. Proved with two separate PHP processes racing for a
balance that only covers one of them: exactly one succeeded, the other was refused, and the
balance landed correct.

**Full coverage or nothing**, as agreed: the method is offered only when the balance covers the
whole order. No total collector, no split payment. A balance that covers part of the basket stays
hidden rather than failing at the end of checkout.

**Where the money moves**, and why there:
- *Debit* on `sales_order_place_after` — Magento dispatches that **before** it saves the order row,
  so throwing on a shortfall stops the order instead of leaving an unpayable one behind.
- *Refund* on `sales_order_creditmemo_refund`, logged rather than thrown: the credit memo already
  exists by then, and throwing would leave the refund half-applied.
- *Cancel* on `order_cancel_after`, taking the amount **from the ledger, not the order total**, so
  a partly refunded order gives back only what is still outstanding and a second cancel gives back
  nothing.

**Only debits are guarded.** An administrator's adjustment may take the balance negative on
purpose — correcting an over-credit is not the same thing as a customer overspending.

The method itself is a virtual type of `Magento\Payment\Model\Method\Adapter` (the supported
route now that `AbstractMethod` is deprecated — core's own offline methods still use the
deprecated base, and this does not), shaped like a core offline method: no `payment_action`, so
placing an order runs no gateway command.

Admin: a *Credit* link on each company row opens a page with the balance, an add/remove form that
accepts both `1234.56` and `1 234,56`, and the full history. Storefront: a read-only balance and
history page behind the new `credit_view` permission. Ten permissions now, every one enforced.

**Verified on ddev** — 113 checks, all passing:
- 81 unit tests (12 new: the row lock, refusal without writing, exact-balance spend, negative
  adjustment allowed, partial-refund-then-cancel arithmetic, double-cancel doing nothing).
- 28 functional checks against the live database, including the two-process race above, the pool
  resolving to the root, the ledger recording the buyer and the root company, and availability for
  member, non-member, guest and blocked company.
- The payment method instantiates through Magento's own payment helper and appears among the
  store's 184 methods; the admin page renders; all 39 XML files validate; routes behave.

One XML slip caught by validation: two `<event name="payment_method_is_active">` blocks in the
same file, which the schema forbids. Merged into one with both observers.

## Known blockers outside this module

`setup:di:compile` fails for the whole project, which means production deployment does too. Two
causes, both pre-existing and unrelated to Tonyapps_B2b:

1. `Tonyapps_ShippingRules/Block/Adminhtml/Rule/Import.php` redeclared the inherited `$formKey` as
   readonly — fatal on PHP 8.4. **Fixed** (property renamed to `$formKeyProvider`) with the user's
   agreement, since it blocked verification.
2. `app/code/Tonyapps/NotifyOnNewBrowserLogin` used the `Tony\NotifyOnNewBrowserLogin` namespace inside
   `Tonyapps/`, which broke `setup:di:compile`. **Fixed 2026-09-30**: renamed to `Tonyapps_NotifyOnNewBrowserLogin`,
   and `cbschuld/browser.php` was added to the root composer.json. It is still disabled.

## Open questions

1. ~~Credit ownership~~ — ✅ built as assumed: one pool per root company, shared by every
   descendant; non-company customers have no balance and are never offered the method.
2. **Company deletion** — ✅ decided as assumed and built: deletion is blocked while children
   exist (re-parent them first); members are detached, not deleted, and released back to the
   default group if the company managed one.
3. **Multi-website / multi-currency** — assumed: companies are global, credit is base-currency
   only. Breaks if websites run different base currencies.
4. **Sales representative** — Adobe assigns an admin user per company. Skipped unless wanted.
5. **Order visibility** — parent-admin descendant orders: merged into "My Orders", or a separate
   "Company Orders" page? Assumed separate page.
6. **REST / GraphQL** — no API surface planned for v1. Needed for a PWA or an ERP integration?
7. ~~Registration edge case~~ — ✅ decided and built: rejected with a message telling them to ask
   their company administrator.
8. **Can a company admin change their own customer group?** Assumed no — admin-only field.
   Note the current sync does not *stop* an admin editing a member's group by hand on the customer
   form; it is simply overwritten the next time that company is saved. Say if it should be locked.
9. **Email recipients** — ✅ decided: the store's General Contact receives new-company
   notifications. Still open for credit adjustments (phase 6).
10. **Riskified** — assumed store-credit-paid orders still run the normal fraud check, no change
    to `Riskified_Decider`.
11. **OneStepCheckout** — currently disabled. If it is re-enabled later, the store-credit method
    needs its Vue checkout wired up too; v1 targets Luma only.

## Review fixes (2026-10-28)

- **Store credit is refunded on cancel** (by order number; `order_id` may be empty when the debit runs), capped at
  the order's cancelled total so a partly invoiced order keeps paying for what was invoiced.
- **The admin company form can't overwrite the balance**: the resource model never writes `credit_balance`; only
  `StoreCredit` moves it, under a row lock with a ledger entry.
- **A blocked or pending company switches off its whole subtree**: permissions, payment rules, store credit and
  the customer group all check every company on the path (`ResourceModel\Company::isActivePath`).
- **Leaving or moving company** takes back only the group the old company gave: a group set by hand is kept, and
  moving to a company without a group drops the old company's pricing. Group changes reach logged-in shoppers on
  their next request (core's customer notification storage).
- **Company Orders** lists orders by the statuses visible on the storefront, like core's own order history, so
  store-credit orders (state `new`) show.
- **Adding users**: an email that already has an account is refused on the storefront; that person never agreed
  to join. An administrator can still assign them. Nobody can change their own role.
- **`credit_use`** ("Pay with the company's store credit") is the planned `place_order`, narrowed to the shared
  money: members still check out with their own card. Only the company admin and roles with it ticked can pay with
  store credit; existing roles need it ticked. Admin-created orders aren't affected.
- **Master switch** (`tonyapps_b2b/general/enabled`) turns off the storefront: company pages, registration, every
  permission check (including viewing colleagues' orders), payment rules and the store credit method. Refunds and
  cancellations of credit already spent keep working, and the admin pages stay available.
- **Credit and companies**: a company that holds a balance or ledger, or whose subtree paid with store credit,
  can't be deleted or moved to another parent (refunds would go to the wrong pool). Block it instead.
- **Admin**: adjusting credit needs its own permission, `Tonyapps_B2b::credit`, and amounts are read in the
  admin's locale ("1,234.50" in English, "1.234,50" in German).
- **Plan corrections**: there is no separate "credit enable" setting; the store credit payment method's own
  *Enabled* flag (Sales > Payment Methods) is that switch. There is no "auto-refund" setting either: credit memos
  and cancellations always return credit to the pool.
