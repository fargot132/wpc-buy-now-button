# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **WPC Buy Now Button for WooCommerce** (version 2.1.7), a WordPress plugin that adds a "Buy Now" button to WooCommerce products, allowing customers to skip the cart page and go directly to checkout. The plugin is developed by WPClever.

## Architecture

### Core Structure
- **Main plugin file**: `wpc-buy-now-button.php` - Contains the main `WPCleverWpcbn` class with all core functionality
- **Includes directory**: Contains modular components:
  - `includes/hpos.php` - WooCommerce HPOS (High-Performance Order Storage) compatibility
  - `includes/dashboard/wpc-dashboard.php` - WPClever dashboard integration
  - `includes/kit/wpc-kit.php` - WPClever essential kit integration

### Key Components

1. **Main Plugin Class** (`WPCleverWpcbn`):
   - Singleton pattern implementation
   - Settings management with `get_settings()` and `get_setting()` methods
   - Localization support via `localization()` method
   - Button positioning via WordPress action hooks

2. **Button Positioning System**:
   - Archive pages: Configurable positions (after title, rating, price, add-to-cart button)
   - Single product pages: Before/after add-to-cart button
   - Shortcode support: `[wpcbn_btn_archive]` and `[wpcbn_btn_single]`

3. **Buy Now Handler**:
   - `handle_buy_now()` method processes buy-now requests
   - Optional cart reset functionality
   - Redirect options: checkout, cart, or custom page
   - Support for simple and variable products

### Frontend Integration
- **CSS**: `assets/css/frontend.css` - Styling for buy-now buttons
- **JavaScript**: `assets/js/frontend.js` - Handles WooCommerce variation events and button states
- **Backend**: Separate CSS/JS files for admin interface

## Development Guidelines

### Plugin Constants
- `WPCBN_VERSION` - Plugin version
- `WPCBN_URI` - Plugin directory URL
- `WPCBN_DIR` - Plugin directory path
- `WPCBN_FILE` - Main plugin file path

### Key Hooks and Filters
- `wpcbn_redirect` - Filter redirect behavior
- `wpcbn_redirect_url` - Filter redirect URL
- `wpcbn_is_valid_product` - Filter product validity for buy-now button
- `wpcbn_btn_archive_text` / `wpcbn_btn_single_text` - Filter button text
- Position filters: `wpcbn_button_position_archive` and `wpcbn_button_position_single`

### Settings Structure
Settings are stored in WordPress options:
- `wpcbn_settings` - Main plugin settings array
- `wpcbn_localization` - Text customization settings

### Variable Product Support
The plugin handles WooCommerce variation events:
- `found_variation` - Enables/disables button based on stock/purchasability
- `reset_data` - Disables button when variations are reset
- `woovr_selected` - Integration with WPC variations plugins

### Category Filtering
Products can be filtered by category using the `cats` setting, allowing selective enabling of the buy-now button.

## File Organization

- `/assets/` - Frontend CSS/JS and images
- `/includes/dashboard/` - Admin dashboard integration
- `/includes/kit/` - WPClever kit integration  
- `/languages/` - Translation files (.pot file included)
- Main plugin files in root directory

This plugin follows WordPress coding standards and WooCommerce integration patterns.