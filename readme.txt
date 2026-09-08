=== LioX Web Azure Translator for TranslatePress ===
Contributors: lioxweb
Tags: translatepress, azure, translator, translation, multilingual
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Microsoft Azure Translator as an automatic translation engine for TranslatePress with language controls and monthly usage limits.

== Description ==

LioX Web Azure Translator for TranslatePress adds Microsoft Azure Translator as an automatic translation engine inside TranslatePress.

The plugin is intended for site owners who already use TranslatePress and want to use their own Microsoft Azure Translator resource for new automatic translations.

Features include:

* Microsoft Azure Translator integration through the Azure Translator REST API.
* API Key, Region and Endpoint configuration inside TranslatePress.
* Support for multiple target languages.
* Optional target-language whitelist.
* Existing TranslatePress translations are not overwritten.
* New untranslated strings can be translated automatically.
* Batch translation support.
* API credential validation.
* Configurable monthly character hard limit.
* Current-month usage and remaining-character display.
* Automatic monthly usage counter reset.

This plugin requires TranslatePress and a Microsoft Azure Translator resource configured by the site administrator.

Development repository: https://github.com/lioxweb/liox-web-azure-translator

== Installation ==

1. Install and activate TranslatePress.
2. Upload and activate this plugin.
3. Go to Settings -> TranslatePress -> Automatic Translation.
4. Enable Automatic Translation.
5. Under Alternative Engines select "Microsoft Azure Translator (LioX Web)".
6. Enter the Azure Translator API Key.
7. If your Azure resource requires it, enter its Region, for example `northeurope` or `westeurope`.
8. Normally keep the default endpoint: `https://api.cognitive.microsofttranslator.com`.
9. Optional: enter a comma-separated target-language whitelist such as `en,fr,de,it,es,nl`. Leave it blank to allow all supported languages.
10. Configure the Azure Monthly Hard Limit if desired. The default is 1,900,000 characters per calendar month. Set it to 0 to disable the plugin-level cap.
11. Save changes.

== Frequently Asked Questions ==

= Does this plugin overwrite translations already stored by TranslatePress? =

No. Existing translations stored by TranslatePress remain unchanged. Azure is used when TranslatePress requests a translation for a string that does not already have a stored translation.

= Can Azure also translate new English strings? =

Yes. If English is an enabled TranslatePress target language and the string is untranslated, Azure can translate it. If you use the optional target-language whitelist, include `en`.

= What does the monthly character limit do? =

The plugin keeps a local counter of source characters sent to Azure by this WordPress installation. Before a translation request is sent, the plugin checks whether it would exceed the configured monthly hard limit. If so, the request is blocked. The local counter resets when the UTC calendar month changes.

= Does the local counter include all usage on my Azure account? =

No. It only tracks translation requests sent by this WordPress installation through this plugin. Usage from other websites, applications, tools, or Azure resources is not included.

== External Service ==

This plugin relies on Microsoft Azure Translator, an external third-party service, to perform automatic translations.

When the site administrator enables automatic translation, selects this Azure engine, and provides their own Azure credentials, untranslated text strings requested by TranslatePress are sent to Microsoft Azure Translator for processing. Source and target language codes are sent with the translation request. The configured Azure API key and, when applicable, Azure region are sent in HTTP request headers to authenticate the request.

The plugin may also query Microsoft's public Translator languages endpoint to determine supported translation languages. That supported-languages request does not require an API key.

The plugin does not intentionally send WordPress user account information, analytics, telemetry, or advertising data. However, if a text string selected for translation itself contains personal or sensitive information, that text will be transmitted to Microsoft as part of the translation request.

Microsoft Azure Translator service:
https://azure.microsoft.com/en-us/products/ai-foundry/tools/translator

Microsoft Azure legal information and service terms:
https://azure.microsoft.com/en-us/support/legal/

Microsoft Privacy Statement:
https://www.microsoft.com/en-us/privacy/privacystatement

Use of Microsoft Azure Translator is subject to the terms, privacy practices, availability, quotas, and pricing of the Microsoft Azure account configured by the site administrator.

== Privacy ==

This plugin does not include its own telemetry, advertising, or user tracking.

The plugin stores its configuration through the TranslatePress machine-translation settings in the WordPress database. The Azure API key is stored there as part of the administrator's configuration. The plugin also stores a local monthly character counter and short-lived cached validation/language-support data in the WordPress database.

See the External Service section above for information about data sent to Microsoft Azure Translator.

== Notes ==

* TranslatePress supports one active automatic translation engine at a time. If Azure is active, new untranslated strings for any allowed target language can use Azure.
* The plugin batches translation requests conservatively and handles longer strings in smaller pieces.
* The plugin uses TranslatePress' own machine-translation logger and quota counter in addition to its local monthly Azure counter.
* The monthly counter includes explicit API credential tests because those tests send a real translation request to Azure.
* Passive frontend credential checks are cached and do not send repeated test translations.
* The local monthly counter only tracks requests made by this WordPress installation.

== Changelog ==

= 1.1.3 =
* Cleaned WordPress Plugin Check warnings before directory review.
* Added documented PHPCS exceptions for intentional atomic and uninstall database operations.
* Prefixed uninstall-scope variables according to WordPress coding standards.
* Shortened the WordPress.org short description to remain within the 150-character limit.

= 1.1.2 =
* Prepared plugin metadata and packaging for WordPress.org submission.
* Added explicit Microsoft Azure Translator external-service and privacy disclosures.
* Added the TranslatePress dependency header.
* Reduced directory tags to the recommended maximum of five.
* Updated the text domain and package structure to match the WordPress.org plugin slug convention.

= 1.1.1 =
* Prevented passive frontend credential checks from sending a real Azure test translation on every page load.
* Added cached credential validation tied to the current API key, region and endpoint.
* Successful real translation requests refresh the cached credential status.
* Simplified the monthly hard-limit description so it is independent of any Azure pricing plan.

= 1.1.0 =
* Added LioX Web monthly Azure hard limit with a default of 1,900,000 characters.
* Added an atomic local usage counter to avoid parallel WordPress requests exceeding the configured cap.
* Requests are blocked before being sent when the remaining monthly allowance is insufficient.
* Added current-month used / remaining usage information to TranslatePress settings.
* API credential tests are included in the monthly counter.

= 1.0.1 =
* Branding updated to LioX Web everywhere visible.
* Target-language guidance updated so English remains enabled for new or missing translations.
* Empty target-language whitelist explicitly means all supported languages, including English.

= 1.0.0 =
* Initial release.
* Azure Translator REST API v3 integration.
* TranslatePress engine selector integration.
* API key, region, endpoint and target-language settings.
* Credential validation.
* Azure supported-language discovery.
* Request batching, long-string handling and TranslatePress quota logging.
