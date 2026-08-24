# API Schema Export — User Guide

## Where it lives

**System → API Schema Export.** The entry appears only for roles holding
*Generate API Schema* (`Muon_ApiSchemaExport::export`).

## Generating a file

1. **Module selectors** — comma-separated. Four forms:
   - exact: `Muon_FileAttachment`
   - vendor namespace: `Muon` (every `Muon_*` module)
   - wildcard: `Magento_Catalog*`
   - everything: `*`

   The field suggests every enabled module as you type.

2. **Formats** — tick one or more:
   - **HTTP client file (.http)** — JetBrains IDEs and the VS Code REST Client
   - **Postman collection (v2.1)** — import straight into Postman
   - **OpenAPI 3.1 schema** — current spec, for client generators and doc sites
   - **Swagger 2.0 schema** — matches what Magento's own `/rest/all/schema` serves

3. **Optional**
   - *File name* — letters, digits, dots, dashes and underscores. Derived from the selection when blank.
   - *Base URL* — defaults to the default store view's.
   - *YAML instead of JSON* — OpenAPI and Swagger only.
   - *One file per module* — a separate document per selected module.
   - *Omit asynchronous and bulk route variants* — drops the `/async` and `/async/bulk` entries.

4. **Generate and Download.** One format downloads the file itself; more than one downloads a ZIP.

## What you get

The `.http` and Postman files carry `{{baseUrl}}` and `{{token}}` variables, plus a companion
environment file in which **the token is empty**. Fill it in locally. The files are meant to be
shared, so nothing that leaves this screen contains a credential.

Path parameters appear in each format's own syntax: `{sku}` in OpenAPI and Swagger, `:sku` in
Postman (its native form), `{{sku}}` in the `.http` file.

## Messages you may see

| Message | Meaning |
|---|---|
| *Enter at least one module selector.* | The selector field was blank. |
| *Select at least one output format.* | No format was ticked. |
| *No REST routes are declared by: X. Nothing to export.* | Those modules are real and enabled, but declare no `webapi.xml` routes. Nothing was generated — deliberately, rather than handing you an empty file that looks fine. |
| *Your role is not allowed to call any endpoint in the selected modules.* | Every endpoint was filtered out by your permissions. |
| *Could not generate the export. See the system log for details.* | Unexpected failure; the detail is in `var/log`. |

## A note on permissions

You only ever receive descriptions of endpoints your own role can call. An administrator restricted
to Catalog exporting `Magento_Sales` will not receive Sales endpoints. The `bin/magento` command
does not filter this way — shell access already implies full trust.

## Screenshots to capture

- `docs/screenshots/admin-export-form.png` — System → API Schema Export, the empty form
- `docs/screenshots/admin-export-validation.png` — the form after submitting with no selector
