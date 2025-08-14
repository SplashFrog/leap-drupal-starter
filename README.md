# Drupal - Starter Kit

This Drupal Module comes with some pre-defined sub-modules to help get a Drupal Website configured, as well as some enhanced functionality.

**WARNING:** While there aren't any strict composer requirements on this package, there are some Drupal requirements / additional contrib modules that are necessary. Please view the `Extend` and review the `Requires:` list for each of the following.

### Administrative Enhancements
This module helps by giving an enhanced version of the general /admin/content, /admin/content/media views. It also introduces some Bulk Actions that will help with Publishing and Archiving revision content, general pathauto patterns, and more.

### Starter Blocks
This module introduces a general block content called 'General', and also gives a usable class to be able to call, send through a keyed (optional, but helpful) array of specified fields for block fields or paragraph fields. What this will do is then take the values of those fields, and set them as twig variables in nested arrays. For blocks, the initiating twig variable is `block_options` and for paragraphs, it is `paragraph_options`. If you set your desired fields when you utilize the class as key => value, the key will be the variable in twig you can then reference. Otherwise, it will be the machine name of the field.


### Smart Paths
This module introduces two content type fields: A Reference Field called `Parent Content` and an Optional Title field called `Optional Path`. Together, along with the pathauto pattern, this will help give your site a way to nest content within their respective paths. In addition, if you update a path that has sub paths associated with it, those other pieces of content will have their paths updated as well.

### Smart Layout Styles
This module introduces a split for the utilization of Layout Builder Styles. Meaning this takes the defined and set customized styles out of the full `attributes.class` and puts them into their own arrays for easier use in designing Layout Builder twig templates and styling. This is also enhanced further for layout block styles. By utilizing a machine naming convention such as `block__{group_name}__{field_name}` for the naming convention of your Layout Builder Styles Groups and Options for blocks, this will make the block form easier to manage, grouping the fields in easy-to-use groups and detail display. It will also take the CSS classes and provide them within twig template variables, beginning with `smart_styles.{group_name}`, as well as a general `smart_styles_combined`. 

### Starter Content
This module creates three different content types: Basic, Landing, and Home Pages. Once you enable and the configuration is imported, you can then disable this module, making it a clean end-result.

### Starter Media
This module helps create the basic media types a site could use: Document, Image, Audio, Remote Video and Local Video. Once you enable and the configuration is imported, you can then disable this module, making it a clean end-result.

### Starter Users
This module helps define additional fields for your users. Once you enable and the configuration is imported, you can then disable this module, making it a clean end-result.
