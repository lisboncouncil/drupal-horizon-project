# LC Base

Provides the node field storages shared by the LC modules:

- `field.storage.node.body`
- `field.storage.node.field_image`

Up to Drupal 11.3 the standard profile created them together with the
`article` and `page` content types. Since Drupal 11.4 it no longer does, so
every module that attaches `body` or `field_image` to its own content type
depends on this module.

## How it works

`hook_install()` creates the storages only when they are missing, so the
module installs cleanly both on a fresh 11.4 site and on an older site that
already has them. The definitions live in `config/optional`.

Drupal checks the config dependencies of every module in an install batch
before installing any of them. For this reason the config of the dependent
modules does not list these two storages among its `dependencies`: Drupal
adds them back when the config is saved.
