TYPO3.CMS-Catharsis
===================

Vision / Catharsis fork of TYPO3 CMS

This is an integration, sandbox, vision and incubator fork of TYPO3 CMS aiming to get bigger things done more quickly. Patches merged to mainline are regularily merged to this this fork, so the systems do not diverge too much.

Current work-in-progress:
* Major rewrite of install tool

The following mainline changes evolved here and were committed upstream:
* Removal of statictemplates extension
* TCA bootstrap refactoring
* System environment check in instal tool.

Basic goals and direction:
* Introduce scoping
* Converge with FLOW and use some parts of it
* Rewrite configuration handling, probably using the FLOW ConfigurationManager
* Throw away some core backports of FLOW and substitute with the native FLOW code (fluid?)
* Refactor TCA and config handling and speed up bootstrap - DONE
* Composer support
* Fix FAL
* Kick out some core parts that are proven to be broken like comma separated values in database
* Find a better solution for soft delete in database
* Substitute ext:form with a working solution (FLOW?)
* Introduce doctrine dbal including migration path for t3lib_db, get rid of current dbal
* Introduce a solid integration / functional test class environment

Development rules from mainline still apply to this project: Coding standards must be followed and broken code is not accepted. Still, some rules are opened a bit, it is for example possible to break certain things regarding backwards compatibily if there are good reasons for it and if the overall project is not harmed.
It is also ok to drop some things that are hard to fight through in mainline, an example is the newline at end of php file and the closing php operator rule. Those things will be accepted after little coordination with us.

For now, important changes that affect backwards compatibility must be documented in MIGRATION.md. There is currently no issue tracker, important notes should be added to TODO.md.

Feel free to contact us if you want to do bigger things here, please send pull requests from your fork. This fork is leaded as a benevolent dictatorship, trusted and coordinated people have merge rights.

The travis-ci.org test execution of every commit and pull request can be found at https://travis-ci.org/lolli42/TYPO3.CMS-Catharsis

Current contributors with merge rights:
* lolli42 - Christian Kuhn
* helhum - Helmut Hummel
* tmaroschik - Thomas Maroschik