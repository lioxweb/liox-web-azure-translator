# Changelog

All notable changes to **LioX Web Azure Translator for TranslatePress** are documented in this file.

## [1.1.1] - 2026-09-08

### Fixed

- Prevented unnecessary Azure API credential test requests from running during normal frontend page loads.
- Fixed monthly usage increasing on every page refresh because of repeated credential validation.
- Improved caching of Azure API credential validation.

### Changed

- Simplified the Monthly Character Limit description.
- Removed references to Azure free-tier allowances from the plugin settings description.

---

## [1.1.0] - 2026-09-08

### Added

- Configurable monthly character hard limit for Azure translation requests.
- Default monthly limit of 1,900,000 characters.
- Current-month character usage tracking.
- Remaining character count display.
- Automatic monthly usage counter reset.
- Protection against sending translation requests that would exceed the configured monthly limit.

---

## [1.0.1] - 2026-09-08

### Changed

- Updated plugin branding from **LioX** to **LioX Web**.
- Updated the translation engine name to **Microsoft Azure Translator (LioX Web)**.
- Improved target-language configuration.
- Added support for using Azure Translator for English and other newly untranslated languages.
- An empty target-language field now allows all configured TranslatePress languages.

---

## [1.0.0] - 2026-09-08

### Added

- Initial public version.
- Microsoft Azure Translator integration for TranslatePress.
- Azure API Key configuration.
- Azure Region configuration.
- Azure Endpoint configuration.
- Support for global and custom Azure Translator endpoints.
- Multiple target-language support.
- TranslatePress automatic translation engine integration.
- Batch translation support.
- API credential testing.
- Language-code normalization for TranslatePress locale codes.
- Compatibility with existing TranslatePress translations.
