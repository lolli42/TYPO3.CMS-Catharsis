This document contains information for upgrading from
TYPO3 CMS master to the Catharsis fork.

It documents breaking changes and notices that should
be taken care of to get it up and running.


* require_once() of core files.
With the namespace change all class files were moved to a
location where they can be found from the autoloader. While
old class names still work through an alias system, the
old class files were kept only for backwards compatibility to
not trigger a fatal in case someone calls require() or
require_once() on them. Those compat files are removed. This
is an issue if old extensions still use those calls. Usually
the calls can be just removed from the extension.


* Removed deprecation hint in bootstrap
If using old 'extCache' setting, deprecation for this is not hinted anymore.
Such code should be done in reports module and maybe handled by a wizard in
install tool, but it is not the scope of the bootstrap to check for such things.


* Move t3lib/fonts to core/Resources/Private/Font
vera.ttf and nimbus.ttf are now located at ext:core/Resources/Private/Font. It
is unlikly, but if you use those fonts in your TypoScript setup, you need
to adapt the file locations.
