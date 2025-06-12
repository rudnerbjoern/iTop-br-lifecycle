# iTop-br-lifecycle

Copyright (c) 2023-2025 Björn Rudner
[![License](https://img.shields.io/github/license/rudnerbjoern/iTop-br-lifecycle)](https://github.com/rudnerbjoern/iTop-br-lifecycle/blob/main/LICENSE)

## What?

Life Cycle Management of Devices, Brands, OS, etc.

## How?

### Class: OS Version

* End of Mainstream Support (EoMSS)
* End of Extended Support / End of Life (EoL)
* End of Extended Security Update (EoESU)

#### Screenshot OS Version

![Lifecycle OS-Version](Screenshots/Lifecycle_OSVersion.png)

### Class: Model

* End Of Life (EoL)
* End Of Service Life (EoSL)

#### Screenshot Model

![Lifecycle Model](Screenshots/Lifecycle_Model.png)

### Audit rules

Adds audit rules for lifecycle management:

* Physical Devices
  * EoL/EoSL passed
  * EoL/EoSL within 30 days
  * EoL/EoSL this year
  * EoL/EoSL next year
* Servers
  * EoMSS/EoL/EoESU passed
  * EoMSS/EoL/EoESU within 30 days
  * EoMSS/EoL/EoESU this year
  * EoMSS/EoL/EoESU next year
* Virtual Machines
  * EoMSS/EoL/EoESU passed
  * EoMSS/EoL/EoESU within 30 days
  * EoMSS/EoL/EoESU this year
  * EoMSS/EoL/EoESU next year
* PCs
  * EoMSS/EoL/EoESU passed
  * EoMSS/EoL/EoESU within 30 days
  * EoMSS/EoL/EoESU this year
  * EoMSS/EoL/EoESU next year

#### Screenshot Audit Rules

![Lifecycle AuditRules](Screenshots/Lifecycle_AuditRules.png)

## iTop Compatibility

The branch [2.7](https://github.com/rudnerbjoern/iTop-br-lifecycle/tree/itop/2.7) is compatible to iTop 2.7 and iTop 3.1.

The branch [main](https://github.com/rudnerbjoern/iTop-br-lifecycle/tree/main) will only be compatible to iTop 3.2.

Versions starting with 2.7.x are kept compatible to iTop 2.7

The extension was tested on iTop 2.7.10 and 3.2.1

## Attribution
