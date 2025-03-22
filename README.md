# Tetloose REST

Enhances the WordPress REST API with camelCase keys, ACF Options, and menu endpoints.

- ✅ Converts all REST response keys to camelCase
- ✅ Registers WordPress Menus in the REST API
- ✅ Exposes ACF Options Pages via REST

## 🧱 Features

### 🔁 1. REST Keys to camelCase

- Works on:
- - Core page responses (rest_prepare_page)
- - ACF page responses (rest_prepare_acf-page)
- - Custom endpoints (menus, options, etc.)

## 🍔 2. WordPress Menus in REST

Registers a custom endpoint to fetch a menu by its ID.

**Endpoint:**

`GET /wp-json/acf/v3/menu/{id}`

**Example:**

`/wp-json/acf/v3/menu/2`

Returns the full menu object and its items in camelCase.

## ⚙️ 3. ACF Options Page in REST

Adds a REST route to fetch ACF fields defined in the Options Page.

**Endpoint:**

`GET /wp-json/acf/v3/options`

Returns all ACF option page fields in camelCase.

## 🚀 Installation

- Add plugin to ~/wp-content/plugins
- Activate the plugin from WordPress Admin → Plugins

## 📁 Folder Structure

```
tetloose-rest/
├── index.php
└── functions/
    ├── acf-options-page.php
    ├── register-menu-rest-route.php
    └── rest-keys-to-camel-case.php
```
