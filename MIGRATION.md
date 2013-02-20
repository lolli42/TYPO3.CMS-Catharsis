This document contains information for upgrading from
TYPO3 CMS 6.0 to the Catharsis fork.

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


* Removed ext:statictemplates from core
The extension static templates is unmaintained and partly broken.
Most functionality was so old or half finished, that nobody
dares to use it anymore. It was removed from core.
If this extension is *really* still needed for whatever reason,
integrators should feel free to maintain and provide it himself.

Some TypoScript MENU objects include javascript files from
statictemplates, which breaks them after removing the extension.
It is expected that these menu objects aren't used in younger
projects anyway.
** GMENU_LAYERS
** TMENU_LAYERS
** GMENU_FOLDOUT


* Removed t3lib_syntaxhl
This class was unused in the core for a long time already and
is removed without deprecation. If you still need it for
whatever reasons (really?), please deliver a copy with
your extension.


* Removed t3lib_formmail
The class is unlikly to be used in the frontend currently. The
call in the frontend rendering process is removed. It is unlikly
your code is affected by this patch, if so, you need to find
a different solution to send mails at this point.


* Removed deprecation hint in bootstrap
If using old 'extCache' setting, deprecation for this is not hinted anymore.
Such code should be done in reports module and maybe handled by a wizard in
install tool, but it is not the scope of the bootstrap to check for such things.


* Move t3lib/fonts to core/Resources/Private/Font
vera.ttf and nimbus.ttf are now located at ext:core/Resources/Private/Font. It
is unlikly, but if you use those fonts in your TypoScript setup, you need
to adapt the file locations.
