# LC Base

Provides the node field storages shared by the LC modules:

- `field.storage.node.body`
- `field.storage.node.field_image`

Up to Drupal 11.3 the standard profile created them together with the
`article` and `page` content types. Since Drupal 11.4 it no longer does, so
every module that attaches `body` or `field_image` to its own content type
depends on this module.

The storages live in `config/optional`: Drupal creates them only when they
are missing, so the module installs cleanly both on a fresh 11.4 site and on
a site that already has them.
