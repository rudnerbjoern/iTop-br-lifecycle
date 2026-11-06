# PHP tests

Run from the repository root with PHP 8.0 or later, SimpleXML, and DOM enabled.
First download the two standalone schemas used by the project's XML files:

```sh
mkdir -p .tmp/schemas/3.2
curl --fail --location https://rudnerbjoern.github.io/iTop-schema/extension.xsd -o .tmp/schemas/extension.xsd
curl --fail --location https://rudnerbjoern.github.io/iTop-schema/3.2/itop_design.xsd -o .tmp/schemas/3.2/itop_design.xsd
```

PowerShell alternative:

```powershell
New-Item -ItemType Directory -Force .tmp/schemas/3.2 | Out-Null
Invoke-WebRequest https://rudnerbjoern.github.io/iTop-schema/extension.xsd -OutFile .tmp/schemas/extension.xsd
Invoke-WebRequest https://rudnerbjoern.github.io/iTop-schema/3.2/itop_design.xsd -OutFile .tmp/schemas/3.2/itop_design.xsd
```

Alternatively, set `ITOP_SCHEMA_DIR` to an existing iTop-schema checkout containing
`extension.xsd` and `3.2/itop_design.xsd`. Then run:

```sh
php tests/run.php
```

On Windows, use the same command with `php.exe` if needed.
No Composer packages, database, or iTop installation are required.
The runner prints each result and exits with `0` on success or `1` on failure.

Two tests validate the manifest and data model against the local XSDs.
Missing schemas fail the tests with setup instructions. Tests run offline after
setup; refresh the cached schemas explicitly when needed. Results apply to the
schema versions present locally.

The release preparation tests check synchronized metadata updates, preservation
of unrelated content, and rejection of invalid versions, dates, and inconsistent
metadata. They do not change the project's release metadata.

The lifecycle tests verify optional date fields, detail and summary placement,
read-only device relationships, and labels in all three bundled languages.
The installer tests execute the real installer with in-memory iTop substitutes:
all 48 audit queries, four categories, and their domain links are checked, along
with the upgrade guard and installation without audit classes.

The substitutes capture OQL without parsing or executing it. These tests do not
establish database behavior, rendered UI behavior, or successful installation
in iTop. Verify these in an installed instance.

For every PR targeting `main`, CI sets up PHP 8.3 with DOM and SimpleXML,
downloads both schemas into `.tmp/schemas`, and runs `php tests/run.php`.
The `PR checks` completion job requires the PHP tests and Gitleaks scan to succeed.
A schema download failure fails the test job. Push-only runs scan with Gitleaks.
For `release/*` PRs, CI also checks that the `release/X.Y.Z` branch version
matches the manifest and module versions.
