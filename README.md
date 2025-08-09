# Creativestyle Sales Plugin

**For demonstration and development purposes.** This plugin implements custom discount logic for Shopware 6 carts.

## Overview

The **Creativestyle Sales Plugin** adds two promotional rules to Shopware 6:

1. **Every Nth Product Free** – configurable via the Shopware Administration (default: every 5th product is free, grouped by name, referenced ID, or type).
2. **Percentage Discount Over Threshold** – configurable threshold and percentage (default: 10% discount if the cart subtotal exceeds 100).

The plugin automatically applies the better of the two discounts and ensures that they do not combine.

## Installation

### Option 1: Using the Shopware Administration
1. Download the ZIP file of the plugin.
2. Upload it via **Extensions → My Extensions** in the Shopware Administration.
3. Install and activate the plugin.

### Option 2: Using the Command Line
1. Clone the repository into the `custom/plugins` directory of your Shopware 6 installation:
   ```
   git clone https://github.com/creativestyle/sales-plugin.git custom/plugins/CreativestyleSales
   ```



2. Run the following commands from the Shopware root directory:

   ```sh
   bin/console plugin:refresh
   bin/console plugin:install --activate CreativestyleSales
   ```

## Configuration

Once activated, go to **Settings → System → Plugins → Creativestyle Sales Plugin** and adjust:

* `nthGroupBy` – how products are grouped when counting towards the “Nth free” discount.
* `nthStep` – which product in the group is free (e.g., 5 for every 5th free).
* `percentThreshold` – cart subtotal required to apply the percentage discount.
* `percentValue` – percentage discount value.

## Development
Run code style checks and tests:

```sh
make cs
vendor/bin/phpunit
```

## License
Free to use, modify, and distribute. No warranty. Include license information when redistributing.