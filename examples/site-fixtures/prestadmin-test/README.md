# Prestadmin second-site test fixture

This fixture defines a separate, synthetic Dendrila site for exercising a second site configuration alongside Vues Imprenables. It is based on the public-facing service site in `jasmin-abernathy/prestadmin57-site` (default branch `main`, inspected 2026-10-01).

## Boundary

This is a local test fixture, not a connection to `prestadmin57.fr`. It contains no production database, credentials, client records, payment data, private uploads, or operational workflow. Do not point the live domain or Prestadmin's existing cPanel deployment at this fixture.

Only public-page copy is represented, and the sample values are fictional. Prestadmin's login, client workspace, dossiers, quotes, invoices, payment confirmation, and SIRET tools remain outside the Dendrila pilot.

## Existing remote data sources

The current Prestadmin code uses live public JSON APIs for company lookup:

- `geo.api.gouv.fr/communes` resolves a town name to a public commune code.
- `recherche-entreprises.api.gouv.fr/search` returns public company-register results. The website also links to official records at `annuaire-entreprises.data.gouv.fr`.

These are APIs, not static content files. They are useful for a separate live connector smoke test and should be exercised with non-personal test queries. Dendrila's site fixture does not fetch them yet: external business actions still require a site-specific `BusinessActionExecutor`, and the core currently returns 501 without one. Keep those upstream checks separate from deterministic CMS snapshot tests; do not persist upstream results as site content unless the Prestadmin connector explicitly needs that behavior.

Prestadmin also accepts client-provided CSV/TSV/TXT files in its paid dossier flow and stores them privately. Those are confidential business data, not a test source; never copy them into Dendrila. The VPN address list fetched by an internal maintenance script is unrelated to the site's editorial content or MMW modules.

## What this fixture tests

- A second `site_id` and brand can be isolated from the Vues Imprenables pilot.
- A public landing-page summary can be projected to MMW v1 as a read-only `form`.
- If write support is deliberately enabled in the disposable local copy, the CMS policy routes changes through review before publication.
- The test configuration uses only documented MMW v1 field types and preserves the existing protocol paths and headers.

Files:
- `site.json`: synthetic site metadata, policy, branding, and MMW module projection.
- `content-type.json`: the small site-specific content model needed by the fixture.
- `content.json`: synthetic starter content with no real client information.

## Current prerequisites and limits

The Dendrila core currently has no configuration-admin screen or discovery manifest. Its MMW authentication routes return 503 until a site supplies an `IdentityProvider`. For a local contract test only, provision a dedicated test database and identity provider; never copy Prestadmin production credentials or use the development token issuer as a deployment login.

The fixture files are not auto-imported by `bin/bootstrap-site.php`. They are reference inputs for the second-site integration and must be wired to a dedicated local/staging bootstrap before an end-to-end MMW test can pass. Do not enable writes or deploy an adapter until authentication, site isolation, review, and rollback have been verified.

## Suggested local smoke test

1. Create a fresh MariaDB database for tests; do not reuse either site's production database.
2. Apply the core migrations and bootstrap `prestadmin_test` with owner `prestadmin_fixture_owner` and `review_before_publish=true`.
3. Install the content type and synthetic content from this directory, then configure the `home-summary` MMW module.
4. Use a test-only identity provider and device token; check `GET /v1/config` and `GET /v1/snapshot`.
5. In a disposable local copy of `site.json`, set the module's `writable` flag to `true`; submit one `update_fields` request and verify that it awaits editorial review before publication.
6. As a separate live check, call the two public upstream APIs with non-personal queries through a Prestadmin-owned connector, and confirm that a temporary upstream failure does not break the CMS snapshot.
7. Confirm a different site token cannot read or mutate this site, then remove the disposable database.

These steps are an integration plan, not evidence that the HTTP deployment, identity provider, connector, or staging environment already exists.
