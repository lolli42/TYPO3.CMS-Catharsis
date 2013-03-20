* Handle deprecated LocalConfiguration settings at a central place (eg. hint
in reports module and handle with wizard in install tool)

* Deprecate typo3_conf_vars[sys][form_enctype] ... BE for sure has several other problems
if file uploads are not allowed. file_upload should be a hard requirement!

* Find a strategy on how to handle Check.php php.ini values if run by cli sapi (reports task?)