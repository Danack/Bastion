Introduction
============

Bastion has two functions; building a satis repository for Composer to use, and building RPMs.

Satis repository
----------------

Bastion allows you to build your own Satis repository. Composer usually talks to Packagist, which is a Satis repository, to find out what versions of packages are available. Building your own Satis repository through Bastion and having Compsoser use it has has the benefits of: 

.. rubric::  Removes dependencies on Packagist and Github

Running Composer without using your own Satis repository puts you at the mercy of both Github and Packagist being up. We all know that this not as reliable as you might like.


.. rubric::  Easier management of packages

Bastion lists all of the packages that are being used in single place. 

Bastion also supports an :ref:`ignorelist` to allow 

For example, imagine that a security hole was found in the Symfony yaml library in version 2.4.3. Without having a central place to manage packages, you would need to go into each project that uses the symfony yaml library and change:

    "symfony/yaml": "~2.4.0"

to

    "symfony/yaml": "~2.4.0, !2.4.3"

 
Having to touch all of your projects to do a security update is not fun to begin with, and are you sure you got them all? Are you sure you didn't forget any? Are you sure that your colleagues didn't miss any?

With Bastion you can just add: 

    symfony/yaml/symfony_yaml_v2.4.3

to the ignoreList and re-run Bastion. That will remove the offending version from your repository. Any future composer update or install will not be able to find the offending version.

.. rubric::  Better security

A long story short, Packagist and Composer do not have perfect security, in fact they are far less than perfect. Although nothing will ever have perfect security, by hosting your own repository, you can set up access to it via a secure VPN rather than trasmitting data over the open internet.

This makes quite a few security concerns just 'go away' as they are no longer relevant.

.. rubric::  Shedloads faster

Because you will only have a small subset of packages in your repository compared to Packagist, Compposer has a much easier time resolving the dependencies required by your projects; this means no more long waits for Composer to figure out which packages to install.

Additionally, the download of the packages should be a lot faster, if you can host the Satis repository in your office, as well as having it in your data-centre, close to where your deploy applications.


Bastion RPM building
--------------------

When you go to install an application, you should be installing from a pre-built package, rather than running 'composer install' during the deployment process. RPM is the most common package format, but unfortunately building RPMs is annoying - it's not that difficult, but it is tricksy.  

Bastion allows you to build an RPM by writing a simple config file.