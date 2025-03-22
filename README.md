# Tetloose REST

Enhances the WordPress REST API with camelCase keys, ACF Fields in REST, ACF Options, and menu endpoints

- ✅ Adds ACF Fields to REST API
- ✅ Converts all REST response keys to camelCase
- ✅ Registers WordPress Menus in the REST API
- ✅ Exposes ACF Options Pages via REST

## 🧱 Features

### 1. 🧩 Add ACF Fields to REST API

Automatically adds Advanced Custom Fields to REST responses for:

- Pages (`/wp-json/wp/v2/pages/:id`)
- Posts (`/wp-json/wp/v2/posts/:id`)
- Any custom post type that is registered with `show_in_rest: true`

ACF fields are attached under the `acf` key, and all keys are camelCased.

**Example Output:**

```json
{
  "id": 2,
  "title": { "rendered": "Homepage" },
  "acf": {
    "heroTitle": "Welcome",
    "heroImage": { ... }
  }
}
```

### 🔁 2. REST Keys to camelCase

- Works on:
- - Core page responses (rest_prepare_page)
- - ACF page responses (rest_prepare_acf-page)
- - Custom endpoints (menus, options, etc.)

### 🍔 3. WordPress Menus in REST

Registers a custom endpoint to fetch a menu by its ID.

**Endpoint:**

`GET /wp-json/tetloose/v1/menu/{id}`

**Example:**

`/wp-json/tetloose/v1/menu/2`

Returns the full menu object and its items in camelCase.

### ⚙️ 4. ACF Options Page in REST

Adds a REST route to fetch ACF fields defined in the Options Page.

**Endpoint:**

`GET /wp-json/tetloose/v1/options`

Returns all ACF option page fields in camelCase.

## 🚀 Installation

- Add plugin to ~/wp-content/plugins
- Activate the plugin from WordPress Admin → Plugins

## 📁 Folder Structure

```
tetloose-rest/
├── index.php
└── functions/
    └── rest-keys-to-camel-case.php
    ├── acf-post-fields.php
    ├── acf-options-page.php
    ├── register-menu-rest-route.php
    ├── core-endpoints-to-camel-case.php
```
