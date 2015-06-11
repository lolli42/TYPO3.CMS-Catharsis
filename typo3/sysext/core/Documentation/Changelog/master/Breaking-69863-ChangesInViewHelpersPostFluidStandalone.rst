===============================================================
Breaking: #69863 - changes in ViewHelpers post-standalone-Fluid
===============================================================

Description
===========

The following ViewHelpers have changed behaviours in Fluid:

* The ``f:case`` ViewHelper does not support ``default`` argument any longer. To indicate which case is the default, use ``f:defaultCase``.
* Tag content of ``f:render`` is no longer ignored and will be output if called with ``optional="1"``.


Impact
======

* A warning about use of an unregistered argument ``default`` will be displayed if templates contain ``f:case`` with ``default`` argument.
* Unexpected template output will be output if templates are rendered which contain ``<f:render partial/section optional="1">will be output now</f:render>``.

Affected Installations
======================

* Any TYPO3 instance that uses a template containing ``f:case`` with ``default`` argument.
* Any TYPO3 instance that uses a template with ``f:render`` with ``optional="1"`` and having content in the ``<f:render>`` tag.


Migration
=========

* Remove the ``default`` option and change ``f:case`` to ``f:defaultCase`` for that case.
* Remove the tag contents of ``f:render``.
