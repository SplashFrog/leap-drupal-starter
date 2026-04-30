# Splash Frog - LEAP Starter

**The architectural foundation for enterprise Drupal 11 applications.**

The LEAP Starter ecosystem is a collection of essential tools, services, and configuration recipes that scaffold a modern, scalable Drupal 11 website. It provides solutions for complex core edge-cases (like nested draft publishing), introduces robust URL hierarchy management, and establishes a low-code design system via Layout Builder.

---

## ✨ Architectural Philosophy

Legacy starter kits often rely on rigid Installation Profiles or brittle `config/install` directories that break when site builders need to customize standard structures.

**The LEAP Approach:**
1. **Decoupled Configuration (Recipes):** We do not force configuration upon module installation. Instead, we use Drupal 11 Configuration Recipes (`drush recipe ...`). This allows site builders to opt-in to the specific content types, media types, or administrative views they need, when they need them.
2. **Service-Oriented Architecture:** Complex business logic (like URL alias cascading or recursive publishing) is housed in strictly typed, PHP 8.3+ injectable services, ensuring the codebase is testable and extensible.
3. **Graceful Degradation:** The modules are built defensively. If a site builder deletes an optional field provided by a recipe, the backend logic degrades gracefully rather than throwing fatal exceptions.

---

## 🛠️ Requirements

- **Drupal:** ^11.3
- **PHP:** >=8.3
- **Core Ecosystem:** `layout_builder`, `media`, `content_moderation`, `pathauto`, `workflows`

---

## 🚀 Installation & Setup

Because this is an ecosystem of sub-modules, you only enable what you need. Each sub-module includes an installation hook that will prompt you with the exact Drush command required to apply its accompanying configuration recipe.

**Example:**
```bash
drush en leap_content
drush recipe modules/contrib/leap-starter/modules/leap_content/recipes/leap_content
```

---

## 🧩 The Starter Modules

### Content Lifecycle (`leap_content`)
Provides the core Content Types (Basic, Landing, Home) configured for Layout Builder and Content Moderation.
*   **The Core Bug:** Drupal Core has a known issue where publishing a Draft node via bulk actions or the moderation widget *fails* to publish any nested Draft revisions of Inline Blocks or Paragraphs attached to that node.
*   **The Fix:** This module provides the `RecursivePublishingService`. It intercepts the `hook_entity_update` cycle, detects if a parent entity was just published, and aggressively traverses its structure to explicitly promote any stuck child entities (Blocks/Paragraphs) to their Default (Live) revision.

### Administrative Enhancements (`leap_admin`)
Replaces the default Drupal content screens with highly optimized Views for Content and Media management.
*   **Bulk Moderation Service:** Provides a robust service and custom Action Plugins for safely transitioning nodes between workflow states (e.g., Bulk Archive, Bulk Publish) across all language translations while maintaining accurate revision logs.
*   **Routing & UI:** Surgically removes redundant core routes (like the standalone "Moderated Content" tab) and injects environment indicators into the Gin admin toolbar.

### Layout Builder Smart Styles (`leap_lb_smartstyles`)
Transforms Layout Builder into a low-code design tool by extending the contrib Layout Builder Styles module.
*   **UI Organization:** Automatically parses style machine names (e.g., `block__background__dark`) to reorganize the messy block configuration form into clean Vertical Tabs.
*   **Twig Bucketing:** Instead of dumping all CSS classes into a single root array, the `SmartStylesManager` extracts and buckets them into granular Twig variables (`layout_container`, `layout_styles`, `smart_styles`). This allows frontend developers to route specific CSS utility classes to deep DOM elements within Single Directory Components (SDCs).

### Block Utilities (`leap_blocks`)
Provides a general-purpose Block type and the essential `OptionsBuilderService`.
*   This service acts as a bridge between Drupal's strict backend machine names (which use underscores) and frontend CSS/SDC requirements (which prefer hyphens). It extracts field values (like color pickers or alignment dropdowns) and sanitizes them into a clean array for Twig consumption.

### Media Types (`leap_media`)
A lightweight bridging module that provides the configuration recipe to scaffold out the site's foundational Media architecture (Document, Image, Audio, Remote Video, Local Video).

### User Accounts (`leap_users`)
A lightweight bridging module that provides the configuration recipe to establish basic user architecture, including administrative roles and essential fields like First Name and Last Name.

---

## 🛡️ License
This module is part of the Splash Frog Ecosystem, the Drupal Ecosystem, and is provided under the GPL-2.0-or-later License.
