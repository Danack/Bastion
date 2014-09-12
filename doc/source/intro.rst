Introduction
============





Bastion Satis building
----------------------







Bastion RPM building
--------------------


When you go to install an application, you should be installing from a pre-built package, rather than running 'composer install' during the deployment process. RPM is the most common package format, but unfortunately building RPMs is annoying - it's not that difficult, but it is tricksy.  

BastionRPM allows you to define all the information about how an application should be installed in a simple format, and then generates 