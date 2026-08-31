# Muon_ApiSchemaExportAdminUi

Admin screen for [`Muon_ApiSchemaExport`](../ApiSchemaExport/README.md): pick modules and formats
under **System**, and download the generated API description files.

## Installation

```bash
bin/magento module:enable Muon_ApiSchemaExportAdminUi
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Usage

**System → API Schema Export.** Enter one or more comma-separated module selectors (the field
offers every enabled module as a suggestion list), tick the formats you want, and submit.

- One format → the file downloads as itself.
- More than one → they download as a ZIP, named from the same base name a single file would use.

Optional fields: a base filename, a base-URL override, YAML instead of JSON, one file per module,
and omitting the async/bulk route variants.

## Permissions

Gated on `Muon_ApiSchemaExport::export`, declared by the core module. Both controllers declare it as
`ADMIN_RESOURCE`, and the menu entry requires it.

**An administrator only ever sees endpoints their role can call.** A plugin registered in
`etc/adminhtml/di.xml` filters the resolved surface against `AuthorizationInterface` before anything
is rendered — the generated document is something an administrator can forward to anyone, so it
must not describe endpoints they are not allowed to invoke. When the filter removes everything, the
screen says so rather than reporting that the modules declare no routes.

The console command is deliberately **not** filtered: shell access already implies full trust, and a
CLI export that silently changed shape with the operator's role would be worse than useless.

## Documentation

- [Technical reference](docs/technical-reference.md)
- [User guide](docs/user-guide.md)
- [CHANGELOG](CHANGELOG.md)

## Compatibility

Magento **2.4.7, 2.4.8 and 2.4.9**; PHP **8.1 – 8.5**. See the
[core module's compatibility table](../ApiSchemaExport/README.md#compatibility) for the verified
package matrix and the two caveats that apply to 2.4.7.

## Requirements

PHP ~8.1 – ~8.5 · `ext-zip` (any version) · `magento/framework` ^103.0.7 ·
`magento/module-backend` ^102.0.7 · `muon/module-api-schema-export` ^1.0.0

## License

OSL-3.0 — see [LICENSE.txt](LICENSE.txt).
