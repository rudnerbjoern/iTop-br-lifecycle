## 📦 Installation

Requires iTop 3.3.0 or later.

1. Download the `br-lifecycle-vX.Y.Z.zip` asset attached to this release.
2. Extract its contents into your iTop installation's `extensions/` directory,
   preserving the included folder structure. The package creates a single
   `extensions/br-lifecycle/` folder containing the manifest and module.
3. Run iTop setup and select **Datamodel: Lifecycle Management** to activate the extension.

### Upgrading from an extension version before 3.3.0

Setup copies `Model.eosl` to `Model.end_of_support` and `OSVersion.eomss` to
`OSVersion.end_of_support` when the native date is empty. If both dates are
set, setup keeps the native value and records both dates in the setup log.
The OS date represents the end of mainstream support.

Both legacy attributes are deprecated in 3.3.0 and will be removed from the
iTop data model in a later release. Update queries, exports and integrations
to read and write the native fields. The upgrade copies values once.

### Verify your download

Download the matching `.zip.sha256` file alongside the ZIP. From the folder
containing both files, verify the package before extracting it:

```sh
sha256sum --check br-lifecycle-vX.Y.Z.zip.sha256
```

On Windows, use `Get-FileHash .\br-lifecycle-vX.Y.Z.zip -Algorithm SHA256`
and compare the result with the hash in the `.sha256` file.

