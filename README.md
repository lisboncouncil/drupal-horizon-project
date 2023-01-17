# Drupal boilerplate for Horizon Project's website

This is a collection of modules and configuration useful for the creation of a new website.
THe modules is organized with 
- A first module **lc_hcommon** where are configured the predefined empty nodes of type page (governance, etc) and the menu configuration. The module also pre-install o uninstall some modules. Check the .install page for more details.
- The **lc_pages** will install some common pages, like cookies and privacy.
- The **lc_section_\*** will install a complete section, with content-type, roles, views, menu entries and permissions

The modules will create:

## Content-types
- Pilot (pilot pages of the project)
- WP (Work package)
- Partner (Partners of the project)

## Views
- Pilot list (block, page)
- Partner list (block, page)
- Work package list (block)

# Roles
- WP Manager
- Partner Manager

# Taxonomies vocabulary and terms
- Country (EU27 countries)

# Pages
- Privacy
- Cookies

# Nodes (empty pages)
- Description of the project
- Governance
- Contact
- Open data

# Menu entries

- Main menu:
  - About
    - Description of the project (see nodes)
    - Partners
    - Governance
  - News
  - Pilots
  - Contact
  
- Footer menu
  - Privacy
  - Cookies
  - Open data
  - Contact 
