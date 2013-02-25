* MENU object instantiation in Hierarchichal menu still prefixes with t3lib_*

* Find a solution / remove the broken MENU objects GMENU_FOLDOUT,
GMENU_LAYERS and TMENU_LAYERS. See the statictemplate removal patch
and MIGRATION.txt for details.

* Handle deprecated LocalConfiguration settings at a central place (eg. hint
in reports module and handle with wizard in install tool)

* Deprecate typo3_conf_vars[sys][form_enctype] ... BE for sure has several other problems
if file uploads are not allowed. file_upload should be a hard requirement!
