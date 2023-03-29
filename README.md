# Audit plugin for Craft CMS 3.x

Audit log for Craft 4.

![Plugin icon](resources/img/icon.png)

_Note: This plugin costs $99.00 through the [Craft Plugin Store](https://plugins.craftcms.com/audit) when used in production._

## Screenshots

![Screenshot of index view](resources/screenshots/audit-index.png)

![Screenshot of details view](resources/screenshots/audit-details.png)

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require superbig/craft-audit

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Audit.

## Audit Overview

Audit automatically keeps an audit log for actions done by logged in users.

## Configuring Audit

```php
<?php
return [
    // How many days to keep log entries around
    'pruneDays'          => 30,

    // Enable logging
    'enabled'            => true,

    // Toggle specific event types
    'logElementEvents'            => true,
    'logChildElementEvents'       => false,
    'logDraftEvents'              => false,
    'logPluginEvents'             => true,
    'logUserEvents'               => true,
    'logRouteEvents'              => true,

    
    // Prune old records when a admin is logged in
    'pruneRecordsOnAdminRequests'          => false,

    // Enable geolocation status
    'enabledGeolocation' => true,
    'maxmindLicenseKey' => '',
    
    // Where to save Maxmind DB files
    'dbPath' => '',
];
```

## Using Audit

As long as the plugin is installed, it will log the following events automatically:

- Creating/saving/deleting elements (including users, Commerce product/variants etc.)
- Saving global sets
- Creating/saving/deleting routes
- Installing/uninstalling and enabling/disabling plugins
- Login/logout

More events like Commerce-specific event handling is planned.

### Geolocation

To enable geolocation lookup with the help of the MaxMind GeoLite2 databases, you first have to generate a license key. 

Add your [MaxMind.com License Key](https://support.maxmind.com/account-faq/license-keys/can-generate-new-license-key/) obtained from the [MaxMind.com account area](https://www.maxmind.com/en/accounts/current/people/current).  

## Clearing old records

You can prune records older than `n` days (configured by the `pruneDays` setting) either by using the console command `./craft audit/default/prune-logs` or by a button on the Audit index screen. 

## Credits

- [Auditing icon by Ralf Schmitzer](https://thenounproject.com/term/auditing/960985)

Brought to you by [Superbig](https://superbig.co)
