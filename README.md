# XiHuan_ColorCaptcha

Admin image CAPTCHA enhancement for Magento 2: optional **per-character colors** and **grayscale noise** for better readability on the backend login (and other admin forms that use the default image CAPTCHA). Storefront CAPTCHA is unchanged.

- **License:** MIT — see [LICENSE.txt](LICENSE.txt).

## Requirements

- Magento 2.4.x (Open Source or Adobe Commerce) with `Magento_Captcha` enabled
- PHP 8.x (match your Magento installation)
- GD extension with FreeType (`imagefttext`) support (same as core image CAPTCHA)

## Installation

### Option A — `app/code` (typical)

1. Ensure the module lives at:

   `app/code/XiHuan/ColorCaptcha/`

2. Enable the module and update the database:

   ```bash
   bin/magento module:enable XiHuan_ColorCaptcha
   bin/magento setup:upgrade
   ```

3. Recompile DI (production mode) and flush caches:

   ```bash
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

### Option B — Composer (path repository)

The package name is **`xsanz/module-color-captcha`** (see [composer.json](composer.json)). In the **Magento project** `composer.json`, add a path repository pointing at this directory, then:

```bash
composer require xsanz/module-color-captcha:*@dev
bin/magento module:enable XiHuan_ColorCaptcha
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Adjust the version constraint if you tag releases (e.g. `^1.0`).

## How to use

### 1. Turn on admin CAPTCHA (core)

Per-character colors only apply when **Admin CAPTCHA** is enabled.

1. In the Admin, go to **Stores → Configuration**.
2. Open **Advanced → Admin → CAPTCHA**.
3. Set **Enable CAPTCHA in Admin** to **Yes** and configure forms, mode, and attempts as needed.

### 2. Enable per-character color mode (this module)

In the same section (**Advanced → Admin → CAPTCHA**), below the standard fields:

1. Set **Enable Per-Character Color CAPTCHA** to **Yes**.
2. Optionally adjust **Text luminance** and **Noise luminance** fields (all are **0–255**, BT.601 luminance).

**Defaults** (from `etc/config.xml`, restorable via **Use Default** in the UI):

| Setting | Default | Purpose |
|--------|---------|--------|
| Enable Per-Character Color CAPTCHA | **No** (opt-in) | Off = stock Laminas image; On = colored glyphs + gray noise |
| Text luminance min | `0` | Darkest allowed brightness for glyphs |
| Text luminance max | `105` | Lightest allowed for glyphs (keep low for contrast on white) |
| Noise luminance min | `140` | Darkest gray for dots/lines |
| Noise luminance max | `160` | Lightest gray for noise |

Keep **text luminance max** clearly **below** **noise luminance min** so letters stay darker than the noise.

<img width="1051" height="475" alt="image" src="https://github.com/user-attachments/assets/1e1565dc-08f9-43fb-ac7a-d324f58ef20d" />

### 3. When you will see it

Admin CAPTCHA appears according to your **Displaying Mode** (e.g. after failed login attempts). Refresh the CAPTCHA image with the reload control next to the image if needed.

<img width="488" height="866" alt="image" src="https://github.com/user-attachments/assets/c8b0962e-f0b9-4e90-b94f-6a3af0d5d1eb" />

## Behavior notes

- **Admin only:** The module replaces `Magento\Captcha\Model\DefaultModel` only in the **adminhtml** area. Customer-facing CAPTCHA is not affected.
- **With the feature off:** Behavior matches core (single-color Laminas image pipeline).
- **With the feature on:** Each character is drawn in a random color within the text luminance bounds; noise is **grayscale** only; distortion uses an RGB-preserving resample so colors remain visible after the wave transform.

## Configuration scope

The CAPTCHA group is available per **website** scope in Configuration. If you use multiple websites, set values under the scope where you manage admin settings (often **Default Config**).

## Uninstall

```bash
bin/magento module:disable XiHuan_ColorCaptcha
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Remove the module files if you no longer need them. Config keys under `admin/captcha/*` for this module may remain in the `core_config_data` table until cleaned up manually if required.

## Support

Issues and improvements: maintain in your project or VCS as appropriate for `XiHuan_ColorCaptcha`.
