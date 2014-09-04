# Nightly

This is a sort of CI/Nightly build engine written in PHP. Rather than configure tests using a GUI or BASH scripting, this tool aims to give programmers the ability to define their CI builds in code. We've started with a bare-bones Drupal install class and plan to make that more robust.

## To-do

* Test runner over and for Drupal
* Artifact generation
* Backup file assignment
* Email notifications
* Build completeness check
* exec() error checking
* Plugin file architecture
* Config file validation