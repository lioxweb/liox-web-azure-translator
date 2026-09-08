=== LioX Web Azure Translator for TranslatePress ===
Contributors: lioxweb
Tags: translatepress, azure, translator, microsoft, translation, woocommerce
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Microsoft Azure AI Translator as an automatic translation engine for TranslatePress.

== Installation ==

1. Install and activate TranslatePress.
2. Upload and activate this plugin.
3. Go to Settings -> TranslatePress -> Automatic Translation.
4. Enable Automatic Translation.
5. Under Alternative Engines select "Microsoft Azure Translator (LioX Web)".
6. Enter the Azure Translator API Key.
7. If your Azure resource is regional or multi-service, enter its Region (for example westeurope).
8. Normally keep the default endpoint: https://api.cognitive.microsofttranslator.com
9. Optional: enter a comma-separated target-language whitelist such as en,fr,de,it,es,nl. Leave it blank to allow all supported languages.
10. Keep the default Azure Monthly Hard Limit at 1,900,000 characters, or choose your own cap.
11. Save changes.

== Notes ==

* Existing TranslatePress translations are not overwritten. TranslatePress only asks the active automatic engine for strings that still need a translation.
* TranslatePress supports one active automatic engine at a time. If Azure is active, new untranslated English strings will use Azure too. Leave the optional target-language whitelist blank to allow all languages, or include en when using a whitelist.
* For broad Azure Translator v3 compatibility, this plugin batches conservatively at up to 25 text elements / 4,800 input characters per request and handles longer strings in smaller pieces. Microsoft documents higher limits for some current NMT endpoints, but the conservative limits keep the integration compatible with the v3 REST contract.
* The plugin uses TranslatePress' own machine-translation logger and quota counter.
* LioX Web also keeps its own atomic monthly Azure request counter. By default it blocks new Azure translation calls at 1,900,000 source characters per UTC calendar month, before a request that would exceed the cap is sent.
* The monthly counter includes explicit API credential tests because those are real Azure translation requests. Passive frontend credential checks are cached and do not send test translations. It only tracks requests sent by this WordPress installation; do not reuse the same Azure resource/key elsewhere if you need this local cap to represent the whole Azure resource usage.
* The API key is stored in the TranslatePress machine-translation settings in the WordPress database, similarly to other API translation engines.

== Azure setup ==

Create an Azure Translator resource, then copy Key 1 or Key 2 from the resource's "Keys and Endpoint" page. A global single-service resource needs the subscription key only. Regional and multi-service resources also require the resource region.

== Changelog ==

= 1.1.1 =
* Prevented passive frontend credential checks from sending a real Azure test translation on every page load.
* Added cached credential validation tied to the current API key, region and endpoint.
* Successful real translation requests refresh the cached credential status.
* Simplified the monthly hard-limit description so it is independent of any Azure pricing plan.

= 1.1.0 =
* Added LioX Web monthly Azure hard limit with a safe default of 1,900,000 characters.
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
