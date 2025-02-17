# LEXO WebP Converter
Automatically converts images to WebP format upon upload.

---
## Versioning
Release tags are created with Semantic versioning in mind. Commit messages were following convention of [Conventional Commits](https://www.conventionalcommits.org/).

---
## Compatibility
- WordPress version `>=4.7`. Tested and works fine up to `6.4.3`.
- PHP version `>=7.4.1`. Tested and works fine up to `8.3.0`.

---
## Installation
1. Go to the [latest release](https://github.com/lexo-ch/webp-converter/releases/latest/).
2. Under Assets, click on the link named `Version x.y.z`. It's a compiled build.
3. Extract zip file and copy the folder into your `wp-content/plugins` folder and activate LEXO WebP Converter in plugins admin page. Alternatively, you can use downloaded zip file to install it directly from your plugin admin page.

---
## Filters
#### - `webpc/admin_localized_script`
*Parameters*
`apply_filters('webpc/admin_localized_script', $args);`
- $args (array) The array which will be used for localizing `webpcAdminLocalized` variable in the admin.

#### - `webpc/enqueue/admin-webpc.js`
*Parameters*
`apply_filters('webpc/enqueue/admin-webpc.js', $args);`
- $args (bool) Printing of the file `admin-webpc.js` (script id is `webpc/admin-webpc.js-js`). It also affects printing of the localized `webpcAdminLocalized` variable.

#### - `webpc/enqueue/admin-webpc.css`
*Parameters*
`apply_filters('webpc/enqueue/admin-webpc.css', $args);`
- $args (bool) Printing of the file `admin-webpc.css` (stylesheet id is `webpc/admin-webpc.css-css`).

#### - `webpc/options-page/capability`
*Parameters*
`apply_filters('webpc/options-page/capability', $args);`
- $args (string) Change minimun user capability for settings page.

#### - `webpc/options-page/parent-slug`
*Parameters*
`apply_filters('webpc/options-page/parent-slug', $args);`
- $args (string) Change parent slug for options page.

#### - `webpc/temporary-disable-period`
*Parameters*
`apply_filters('webpc/temporary-disable-period', $args);`
- $args (int) Change temporary disable period in mins.

#### - `webpc/dashboard-widget/capability`
*Parameters*
`apply_filters('webpc/dashboard-widget/capability', $args);`
- $args (string) Specify the capability that can see the dashboard widget.

#### - `webpc/dashboard-widget/date-format`
*Parameters*
`apply_filters('webpc/dashboard-widget/date-format', $args);`
- $args (string) Specify the date format for displaying the disable period in message. The default format is `d.m.Y H:i:s`.

---
## Actions
#### - `webpc/init`
- Fires on LEXO WebP Converter init.

#### - `webpc/localize/admin-webpc.js`
- Fires right before LEXO WebP Converter admin script has been enqueued.

#### - `webpc/plugin-temporarily-disabled`
- Fires when the plugin is temporarily disabled via the dashboard widget.

#### - `webpc/temporary-disablement-has-ended`
- Fires when the plugin is re-enabled via the dashboard widget or after temporary disable period.

---
## Changelog
Changelog can be seen on [latest release](https://github.com/lexo-ch/webp-converter/releases/latest/).
