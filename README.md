# LioX Web Azure Translator for TranslatePress

Microsoft Azure Translator integration for TranslatePress.

This plugin adds Microsoft Azure Translator as an automatic translation engine for TranslatePress.

## Features

- Microsoft Azure Translator integration
- API Key, Region and Endpoint settings
- Support for multiple target languages
- Monthly translation safety limit
- Current monthly usage tracking
- Automatic reset of monthly usage counter
- Compatible with existing TranslatePress translations
- Existing translations are not overwritten
- New untranslated strings can be translated automatically
- API credential testing
- Batch translation support

## Requirements

- WordPress
- TranslatePress
- Microsoft Azure Translator resource

## Installation

1. Download the plugin ZIP.
2. Go to **WordPress → Plugins → Add New → Upload Plugin**.
3. Upload and activate the plugin.
4. Go to **Settings → TranslatePress → Automatic Translation**.
5. Select **Microsoft Azure Translator (LioX Web)**.
6. Enter your Azure API Key, Region and Endpoint.
7. Save the settings.

## Monthly Safety Limit

The plugin includes a configurable monthly translation limit.

Default:

```text
1,900,000 characters per calendar month
