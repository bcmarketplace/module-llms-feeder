# LLMsFeeder for Magento 2

A Magento 2 module that automatically collects store content (Products, Categories, CMS Pages, and Company information) and transforms it into a structured markdown format suitable for Large Language Models (LLMs) training and analysis.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Store Selection Feature](#store-selection-feature)
- [Usage](#usage)
- [Scope-Based Generation](#scope-based-generation)
- [File Output](#file-output)
- [Custom Router Implementation](#custom-router-implementation)
- [Multi-Store Support](#multi-store-support-not-supported-in-community-edition)
- [Cron Jobs](#cron-jobs)
- [Technical Architecture](#technical-architecture)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

The LLMsFeeder module automatically generates comprehensive LLMs content files for your selected store. The module creates a single file and includes a custom router that serves the content directly. This is ideal for:

- **LLM Training**: Providing structured data for AI model training
- **Content Analysis**: Analyzing store content and structure
- **SEO Optimization**: Understanding content relationships and gaps
- **Documentation**: Creating comprehensive store content documentation

The module intelligently collects data from your selected store, processes it through configurable filters, and generates a clean, well-formatted markdown file.

## ✨ Features

### Core Functionality
- **Automatic Content Collection**: Gathers products, categories, CMS pages, and company information
- **Single Store Focus**: Processes content from your selected store only
- **Store Selection Configuration**: Choose which store's content to use for LLMs generation
- **Custom Router Implementation**: Automatically intercepts `/llms.txt` requests and serves the selected store's content
- **Intelligent Sitemap Integration**: Automatically derives company information from existing sitemaps
- **Dynamic Cron Configuration**: Automatically manages cron job schedules based on admin settings
- **Real-time File Status**: Admin interface shows file existence and provides direct access links

### Content Processing
- **Smart Filtering**: Only includes active, visible products and categories
- **Content Sanitization**: Removes HTML tags and normalizes text content
- **Meta Description Integration**: Includes meta descriptions for better content context
- **URL Generation**: Creates proper URLs for all content items

### Performance & Reliability
- **Caching System**: Implements intelligent caching to improve performance
- **Memory Optimization**: Processes large datasets in chunks to avoid memory issues
- **Error Handling**: Graceful error handling with comprehensive logging
- **File Locking**: Ensures safe concurrent file operations

## 🔧 Requirements

### System Requirements
- **PHP**: 8.1, 8.2, or 8.3
- **Magento**: 2.4.7 or higher
- **Magento Framework**: ^103.0.4

### Dependencies
- `magento/module-config`: ^101.2.0
- `magento/module-store`: ^101.1.0
- `magento/module-backend`: ^102.0.0
- `magento/module-cron`: ^100.4.0
- `magento/module-cms`: ^104.0.0
- `magento/module-catalog`: ^104.0.0

## 📦 Installation

### Method 1: Composer Installation (Recommended)

```bash
composer require bcmarketplace/module-llms-feeder
bin/magento module:enable BCMarketplace_LLMsFeeder
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:clean
```

### Method 2: Manual Installation

1. **Download the module** to your Magento installation:
   ```bash
   cd app/code/BCMarketplace/
   git clone [repository-url] LLMsFeeder
   ```

2. **Enable the module**:
   ```bash
   bin/magento module:enable BCMarketplace_LLMsFeeder
   ```

3. **Run Magento setup**:
   ```bash
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy
   bin/magento cache:clean
   ```

## ⚙️ Configuration

### Admin Configuration

Navigate to **Stores > Configuration > BC Marketplace > LLMs Feeder** to configure the module:

#### General Settings
- **Enable Module**: Enable/disable the module for the current scope
- **Store for LLMs Generation**: Select which store's content should be used for generating the llms.txt file (default scope only)
- **Site Description**: Provide a general description of your company/site
- **LLM Instruction**: Define essential details such as important notes, brand voice, tone, target audience, and specific directives to guide LLM
- **Content Types**: Multiselect field containing "CMS Page", "Product", "Category" - if all are selected, then CMS pages, products, and categories are included in llms.txt, else only selected types are included
- **Custom File Directives**: Optional textarea field to add custom content to the end of the llms.txt file (disclaimers, links to terms of service, or other final instructions for the LLM)
- **Company Info Pages**: Manually specify CMS page identifiers for company information (only used when sitemap is not available)

#### Generation Settings
- **Frequency**: Choose how often the file should be generated
    - Daily
    - Weekly
    - Monthly
- **Generation Time**: Set the specific time for generation (24-hour format, e.g., 02:00)
- **File Status**: View the current status of the generated file for the selected store

### Store Selection Feature

The module includes a **Store Selection** configuration that allows you to specify which store's content should be used for LLMs generation:

#### Key Features
- **Default Scope Only**: The store selection is only available at the default configuration scope
- **Single Store Focus**: Generate content for one specific store only
- **File Status Integration**: The file status display shows information for the selected store
- **URL Generation**: The "View File" link points to the selected store's content
- **Custom Router Integration**: The custom router serves content from the selected store

#### How It Works
1. **Configuration**: Select your desired store from the "Store for LLMs Generation" dropdown
2. **Generation**: When you generate LLMs content, only the selected store's data is processed
3. **File Creation**: A single `llms.txt` file is created
4. **Access**: Access the content via `https://yourdomain.com/llms.txt` (serves the selected store's content)

#### Benefits
- **Focused Content**: Generate content for your primary store
- **Performance**: Fast generation and optimized file sizes
- **Simplicity**: Single file to manage
- **Targeted Training**: Perfect for LLM training focused on your store's content

### Sitemap Integration

The module automatically detects if a sitemap exists at `<base_url>/sitemap.xml`. When a sitemap is present:

- **Automatic Company Info**: Company links are automatically populated from the sitemap
- **Field Disabling**: The "Company Info Pages" field is automatically disabled
- **Fallback**: If no sitemap exists, manual CMS page selection is used

## 🚀 Usage

### Manual Generation

Generate the LLMs file manually using the console command:

```bash
# Generate for all stores
bin/magento llms:generate

# Generate for a single store
bin/magento llms:generate --stores="1"
```

### Automatic Generation

The module automatically generates the file based on your configured schedule. The cron job is automatically managed and updated when you change the frequency or time settings.

## 🎯 Content Generation

The module generates LLMs content for your selected store using a simple and efficient process.

### Admin Interface Generation

When using the "Generate Now" button in the admin interface:

1. **Location**: Navigate to: Stores > Configuration > BC Marketplace > LLMs Feeder
2. **Action**: Click "Generate Now" button
3. **Result**: Generates LLMs content for the selected store only

### Console Command Generation

The console command provides simple generation:

```bash
# Generate for the selected store
bin/magento llms:generate
```

### Generation Process

The module uses the following logic to determine which store to process:

1. **Store Selection Config**: Uses the store selected in the configuration
2. **Fallback**: If no store is selected, uses the default store

### Benefits of Single Store Generation

- **Performance**: Fast generation with optimized processing
- **Resource Efficiency**: Lower memory usage and faster execution
- **Simplicity**: Single file to manage and maintain
- **Focused Content**: Perfect for targeted LLM training
- **Incremental Updates**: Update content incrementally across stores

### File Locations

The module generates files in the following locations:

#### Store-Specific Content Files
```
pub/llms/{store_code}/llms.txt
```

#### LLMs Renderer File
```
pub/llms.txt
```

### Accessing LLMs Content

You can access LLMs content via your web server at:
```
https://your-domain.com/llms.txt
```

The renderer will automatically serve the appropriate store-specific content based on the request context. See [File Output](#file-output) and [Custom Router Implementation](#custom-router-implementation) for details.

## 📄 File Output

The module generates two types of files:

### 1. Store-Specific LLMs Files

Store-specific content is saved to:
```
pub/llms/{store_code}/llms.txt
```

For example:
```
pub/llms/default/llms.txt
```

### 2. LLMs Renderer File

A PHP renderer file is installed at:
```
pub/llms.txt
```

This file automatically serves the appropriate store-specific content based on the request context.

### Content Structure

Each store-specific `llms.txt` file follows this exact structure:

```markdown
# [Company Name]

> [Company Description]

## Company

- [About Us](https://example.com/about) about company
- [Contact](https://example.com/contact) contact information

## CMS Pages

- [Home](https://example.com)
- [Privacy Policy](https://example.com/privacy-policy)

## Categories

- [Electronics](https://example.com/electronics) : Electronics category meta description
- [Clothing](https://example.com/clothing) : Clothing category meta description

## Products Resources

- [iPhone 15](https://example.com/iphone-15) : Latest iPhone with advanced features
- [Samsung Galaxy](https://example.com/samsung-galaxy) : Premium Android smartphone
```

### Content Rules

- **Company Section**: Uses blockquote format for better readability
- **Categories**: Include meta descriptions when available
- **Products**: Listed by product name (not short description) with meta descriptions
- **CMS Pages**: Simple list format without subheadings
- **No Variations**: Product variations are excluded from the output

### Access Methods

Once configured, you can access LLMs content through various methods:

#### 1. Default Store Access
```
https://yourdomain.com/llms.txt
```

#### 2. Store-Specific Access via Subdomain
```
https://store1.yourdomain.com/llms.txt
https://store2.yourdomain.com/llms.txt
```

#### 3. Store-Specific Access via Custom Header
```bash
curl -H "X-Store-Code: store1" https://yourdomain.com/llms.txt
```

### Security Considerations

- The renderer file includes security checks to prevent direct access
- Content is served with appropriate cache headers (1 hour cache)
- 404 errors are returned for non-existent store codes
- The file falls back to the default store if the requested store doesn't exist

## 🌐 Multi-Store Support (Not supported in Community Edition)

The module fully supports multi-store Magento installations with advanced scope-based generation capabilities:

### Store-Specific Processing
- **Individual Processing**: Each store is processed independently
- **Scope Checking**: Only processes stores where the module is enabled
- **Separate Files**: Each store gets its own LLMs file in `pub/llms/{store_code}/llms.txt`
- **Dynamic Rendering**: The main `pub/llms.txt` file dynamically serves the appropriate store content
- **Store-Specific URLs**: Maintains proper store-specific URLs for all content
- **Fallback Support**: Falls back to default store if requested store doesn't exist
- **Selective Generation**: Generate content for specific stores or websites based on admin scope

### Configuration
- **Store-Specific Settings**: Each store can have different module settings
- **Scope Inheritance**: Website and store-level configurations inherit from default
- **Independent Control**: Enable/disable the module per store as needed
- **Scope-Aware Generation**: Generate button automatically adapts to current admin scope

### Scope-Based Generation Features

#### Admin Interface
- **Automatic Scope Detection**: The "Generate Now" button automatically detects the current admin scope
- **Store-Level Generation**: Generate content for a single store when in store scope
- **Website-Level Generation**: Generate content for all stores under a website when in website scope
- **Global Generation**: Generate content for all stores when in default scope

#### Console Commands
- **Error Handling**: Gracefully handles invalid store IDs with appropriate logging
- **Backward Compatibility**: Default behavior processes all stores when no parameters provided

### File Status Display

The module provides file status checking for the selected store:

#### File Status
- **Checks**: If `llms.txt` exists for the selected store (`pub/llms.txt`)
- **If file exists**: Shows "✓ File exists for selected store: [Store Name]" + clickable "View File" link
- **If file doesn't exist**: Shows "✗ File has not been generated yet for selected store: [Store Name]"

## ⏰ Cron Jobs

### Automatic Management
The module automatically manages cron job configuration:

- **Dynamic Scheduling**: Cron expression updates automatically when frequency/time changes
- **Configuration Persistence**: Settings are saved to the database and applied immediately
- **Cache Management**: Configuration cache is cleared to ensure changes take effect

### Manual Cron Setup
If you prefer manual cron management, add this to your crontab:

```bash
# LLMs Feeder - runs daily at 2 AM
0 2 * * * /path/to/magento/bin/magento llms:generate
```

## 🚀 Custom Router Implementation

The module includes a custom router system that eliminates the need for web server configuration (Apache/Nginx) while serving the selected store's content.

### How It Works

1. **Request Interception**: The custom router intercepts all requests to `/llms.txt`
2. **Content Serving**: Serves the content from the selected store configuration
3. **Direct Access**: Provides direct access to the selected store's LLMs content
4. **Automatic Fallback**: Falls back to default store if selected store doesn't exist

### Benefits

- **Zero Configuration**: Works out of the box without Apache/Nginx changes
- **Simple Access**: Direct access to your selected store's content
- **Performance Optimized**: Includes proper caching headers and error handling
- **Seamless Integration**: Fully integrated with Magento's routing system

### Access Methods

Once configured, access LLMs content through:

```bash
# Direct access to selected store's content
curl https://yourdomain.com/llms.txt
```

## 🏗️ Technical Architecture

### Core Components

#### Models
- **`Generator`**: Main orchestration class that coordinates the generation process
- **`DataProcessor`**: Handles data collection from Magento collections with caching
- **`MarkdownGenerator`**: Transforms collected data into markdown format
- **`SitemapProcessor`**: Processes sitemap data for company information

#### Configuration
- **`Config\Backend\Cron`**: Manages dynamic cron job configuration
- **`Config\Source\Frequency`**: Provides frequency options for admin interface
- **`Config\Source\Store`**: Provides store options for the store selection dropdown
- **`Config\Source\ContentTypes`**: Provides content type options for the multiselect field
- **`Helper\Data`**: Provides configuration access, utility methods, and centralized constants

### Data Flow

1. **Configuration Check**: Verifies module is enabled
2. **Store Selection**: Determines target store based on:
    - **Store Selection Config**: Uses the selected store (default scope only)
    - **Fallback**: Uses default store if no store is selected
3. **Store Processing**: Processes the selected store only
4. **Data Collection**: Gathers products, categories, CMS pages, and company data for the selected store
5. **Content Processing**: Sanitizes and formats content based on configured content types
6. **Markdown Generation**: Transforms data into structured markdown with LLM instructions
7. **File Writing**: Saves content to `pub/llms.txt` with proper locking

### Caching Strategy

- **Cache Tags**: Uses `llms_feeder` cache tag for easy invalidation
- **Cache Lifetime**: 1 hour default with configurable settings
- **Cache Keys**: Store-specific cache keys for multi-store support
- **Graceful Degradation**: Continues operation if caching fails

### Centralized Constants

The module uses centralized constants to avoid hardcoded filenames:

- **`Helper\Data::LLMS_FILENAME`**: Centralized constant for the filename (`llms.txt`)
- **Consistent Usage**: All classes use this constant instead of hardcoded strings
- **Easy Maintenance**: Change filename in one place to update throughout the module

## 🧪 Testing

### Test Coverage

The module includes comprehensive test coverage for:

- **Unit Tests**: All core classes and methods (69 tests, 152 assertions)
- **Integration Tests**: Admin interface and file operations
- **Edge Cases**: Empty data, disabled modules, exceptions
- **Store Selection Feature**: Tests for store selection configuration and processing
- **Content Type Filtering**: Tests for CMS pages, products, and categories filtering
- **LLM Instructions**: Tests for custom instruction integration
- **Single Store Generation**: Tests for selected store processing and error handling
- **Console Commands**: Tests for generation command execution
- **File Status Display**: Tests for file checking and link generation
- **Custom Router**: Tests for request interception and content serving

## 🔍 Troubleshooting

### Common Issues

#### File Not Generated
1. **Check Module Status**: Verify the module is enabled in admin
2. **Check Permissions**: Ensure `pub/` directory is writable
3. **Check Logs**: Review Magento logs for error messages
4. **Manual Generation**: Try running `bin/magento llms:generate` manually

#### Cron Job Not Running
1. **Check Cron Status**: Verify Magento cron is running
2. **Check Configuration**: Review frequency and time settings
3. **Clear Cache**: Run `bin/magento cache:clean`
4. **Check Logs**: Review cron logs for errors

#### Empty or Incomplete Content
1. **Check Store Status**: Ensure the selected store is active and enabled
2. **Check Product Visibility**: Verify products are visible in catalog
3. **Check Category Status**: Ensure categories are active
4. **Check CMS Pages**: Verify CMS pages are active and accessible
5. **Check Module Settings**: Verify the module is enabled and store is selected

#### File Status Not Showing Correctly
1. **Check Store Selection**: Verify a store is selected in the configuration
2. **Check File Permissions**: Ensure the web server can read the generated files
3. **Clear Cache**: Run `bin/magento cache:clean`
4. **Check File Location**: Verify the file exists at `pub/llms.txt`


### Log Files

Check these log files for debugging information:

- `var/log/system.log` - General system logs
- `var/log/exception.log` - Exception details
- `var/log/cron.log` - Cron job execution logs

## 📚 Documentation

### User Manual

A comprehensive user manual for administrators is available at:
```
user-manual.txt
```

The user manual covers:
- Installation and setup instructions
- Configuration options and best practices
- Step-by-step usage guides
- Multi-store configuration
- Troubleshooting common issues
- Support information

### Technical Documentation

For developers and technical users, this README provides:
- Technical architecture details
- API documentation
- Testing procedures
- Contributing guidelines

## 🤝 Contributing

### Code Standards

- **PHP**: Follow PSR-12 and Magento coding standards
- **Testing**: Maintain 100% test coverage for new features
- **Documentation**: Update README and inline documentation
- **Performance**: Consider memory usage and processing time


## 📝 Changelog

### Version 1.0.0 (February 2026)

#### ✨ New Features
- **Store Selection Configuration**: Added ability to select a specific store for LLMs generation (default scope only)
- **Enhanced Content Configuration**: Added LLM Instruction, Content Types, and Custom File Directives fields
- **Improved File Status Display**: File status now shows information for the selected store only
- **Custom Router Integration**: Router now serves content from the selected store
- **Single Store Focus**: Simplified to work with one selected store instead of multiple stores

#### 🔧 Improvements
- **Performance**: Faster generation with single store processing
- **User Experience**: Simplified configuration with focused store selection
- **File Management**: Single file management at `pub/llms.txt`
- **Simplified Access**: Direct access without store codes in paths or query parameters

#### 🧪 Testing
- **Comprehensive Test Coverage**: All 69 unit tests passing with 152 assertions
- **New Test Cases**: Added tests for store selection, content type filtering, and LLM instructions
- **Fixed Test Issues**: Resolved PHPUnit compatibility issues and method configuration problems

#### 📚 Documentation
- **Updated README**: Added comprehensive documentation for new features
- **Updated User Manual**: Added detailed instructions for store selection configuration
- **Configuration Guide**: Enhanced configuration documentation with new fields

## 📄 License

This module is licensed under the Open Software License v. 3.0 (OSL-3.0).

## 📞 Support

For support and questions:

- **Email**: rbaako@baakoconsultingllc.com
- **Website**: https://baakoconsultingllc.com
- **Issues**: Please use the GitHub issues page for bug reports

---

**Developed by Raphael Baako** - Empowering businesses with innovative Magento solutions.
