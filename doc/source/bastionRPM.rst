


Bastion RPM config
==================

The config to build an RPM package from a composer'd application should be in the root directory of the project in a file named `bastion.php`. The entries that should be be set are:

.. rubric::  unixUser

The unix user that the package files will be installed as. Additionally a user will be created for that user name with no password.

.. rubric::  unixGroup

Optional - the unix user-group that the package will be installed as. Defaults to www-data. 

.. rubric::  dataDirectories

The data directories entry defines a set of directories that will be created when the package is installed. Each directory needs to have 4 elements defined:

* The file mode specified in octal - most directories will need 0755

* Unix user that owns the directory.

* Unix group that the directory belongs to.

* The directory name relative to the directory that the application is installed into.


For example, the entry:

```[0775, 'blog', 'www-data', "var/log"]```

Would create the directory var/log as a sub-directory from where the application was installed, with the user set to 'blog' and group 'www-data'.


.. rubric::  crontabFiles

Defines a set of files that will be copied from the projects directory into the /etc/crond.d directory. This allows applications to manage their crontab entries, without having to edit the main crontab file.

.. rubric::  installFies

A set of files that will be copied from the application to somewhere else on the system. Each entry requires

* src - the source location of the file, relative to the root of the project.
 
* dest - the destination the file should be copied to. This should probably be an absolute path.

For example: 

.. code-block:: php

    [
        'src' => "autogen/logQueueProcessor.supervisord.conf",
        'dest' => "/etc/supervisord.d/logQueueProcessor.supervisord.conf"
    ],

.. rubric::  srcFiles

The list of files in the root directory that should be installed with the application


.. rubric::  srcDirectories

The list of directories that should be installed with the application.


.. rubric::  buildScripts

The build scripts are run after `composer install` is run and before the RPM package is generated. i.e. it is possible to include any files that are built by the build scripts in the deployable RPM package. 

.. rubric::  installScripts

*TODO* Implement install scripts

The install scripts are run after the RPM package is installed


Example config file
-------------------

.. code-block:: php

    $intahwebzConfig = [
        
        'unixUser' => 'intahwebz',
    
        'dataDirectories' => [
            [0775, 'intahwebz', 'www-data', "var"],
            [0775, 'intahwebz', 'www-data', "var/cache"],
            [0775, 'intahwebz', 'www-data', "var/log"],
            [0775, 'intahwebz', 'www-data', "var/compile/templates/"],
            [0775, 'intahwebz', 'www-data', "var/session/"]
        ],
        'crontabFiles' => [
            "conf/backup_crontab",
        ],
    
        'installFies' => [
            [
                'src' => "autogen/logBackground.supervisord.conf",
                'dest' => "/etc/supervisord.d/logBackground.supervisord.conf"
            ],
            [
                'src' => "autogen/basereality.nginx.conf",
                'dest' => "/etc/nginx/sites-enabled/basereality.nginx.conf"
            ],
            [
                'src' => "autogen/basereality.php-fpm.conf",
                'dest' => "/etc/php-fpm.d/basereality.php-fpm.conf"
            ]
        ],
        'srcFiles' => [
            "composer.json",
            "composer.lock" 
        ],
        'srcDirectories' => [
            "basereality", 
            "conf",
            "data",
            "fonts",
            "intahwebz",
            "lib",
            "node",
            "scripts",
            "src",
            "templates",
            "tools",
            "vendor"
        ],
        
        'buildScripts' => [
            '/usr/local/bin/php tools/cli.php configurate centos',
            '/usr/local/bin/php tools/cli.php generateCSS',
            '/usr/local/bin/php tools/cli.php genXDomainObjects',
        ]
    ];