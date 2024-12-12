# WooCommerce Demo Products Importer

A WordPress plugin that uses OpenAI (GPT-4 & DALL-E 3) to generate demo products for your WooCommerce store. The plugin creates realistic product descriptions, features, and high-quality product images automatically in multiple languages.

## Features

- 🤖 AI-powered product generation using GPT-4
- 🖼️ Professional product images using DALL-E 3
- 📦 Generate 1-25 products at once
- 🌐 Support for 10 different languages
- 🏷️ Use predefined product types
- 📁 Save to any WooCommerce category
- 🔄 Automatic SKU generation
- 📝 Detailed product descriptions and features
- 🏷️ Automatic tag generation
- 📊 Stock management
- 📐 Product dimensions and weight
- 💰 Regular and sale prices

## Requirements

- WordPress 5.0 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- OpenAI API key

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and choose the downloaded file
4. Activate the plugin
5. Go to WooCommerce > Import Demo Products
6. Enter your OpenAI API key and save

## Usage

1. Navigate to WooCommerce > Import Demo Products
2. Enter your OpenAI API key (if not already done)
3. Select the number of products you want to generate (1-25)
4. Choose the type of products to generate (e.g., Electronics, Clothing)
5. Select the WooCommerce category where products will be saved
6. Choose your preferred content language
7. Click "Generate and Import Products"
8. Wait for the products to be generated with their images

## Supported Languages

- English (en)
- German (de)
- Spanish (es)
- French (fr)
- Italian (it)
- Dutch (nl)
- Polish (pl)
- Portuguese (pt)
- Russian (ru)
- Chinese (zh)

## Product Types

Available product types for generation:
- Electronics
- Clothing
- Books
- Home & Garden
- Sports & Outdoors
- Beauty & Personal Care
- Toys & Games
- Food & Beverages
- Jewelry
- Art & Crafts

## Generated Product Data

Each product includes:
- Product name
- SKU
- Regular price
- Sale price (optional)
- Stock quantity
- Stock status
- Weight
- Dimensions (length, width, height)
- Short description
- Long description
- Features list
- Tags
- Professional product image

## API Usage

The plugin uses two OpenAI APIs:
- GPT-4 for product data generation
- DALL-E 3 for image generation

Please note that using this plugin will consume your OpenAI API credits. Each product generation uses:
- One GPT-4 API call for product data
- One DALL-E 3 API call for the product image

## Support

For bug reports and feature requests, please use the GitHub issues page.

## Credits

Developed by Tobias Lorsbach

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Product generation with GPT-4
- Image generation with DALL-E 3

### 1.1.0
- Added support for product dimensions
- Added support for stock management
- Added SKU generation

### 1.2.0
- Added support for sale prices
- Improved image generation prompts
- Enhanced error handling

### 1.3.0
- Added multi-language support (10 languages)
- Separated product type selection from category selection
- Improved category handling with WooCommerce integration
- Enhanced error messages and validation

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.