:tocdepth:
    2
    
Installation
============

To install Bastion you should either `git clone https://github.com/danack/bastion` or download it directly from Github.

*TODO* Provide prebuilt RPM files. At some stage I hope to provide prebuilt RPMs of Bastion, however I would only do that with SSL enabled on the server, and that's not a priority for me to do just yet.


Config
------

Running `php src/bastion.php` will take you through a series of prompts to generate a config file.

The config file must be named `bastionConfig.php` and can be placed either in the root directory of Bastion, or in the directory above that. Having the config outside the Bastion allows you to not have Github and/or S3 keys in a directory managed by Git.

The only requirement for the config file is that it must return a Bastion\Config object. The setup procedure will do this for you, alternatively you can just copy and paste the example config below, and insert the appropriate values yourself.


.. _ignorelist:

Ignore list
~~~~~~~~~~~

When Bastion runs it will sometimes encounter errors in particular tags of a repo e.g. missing Composer files, invalid JSON in the composer.json. When this happens it adds that repo and tag to the ignoreList.txt file.

Any repo + tag listed in it will be ignored by Bastion and not downloaded again until you manually remove it's entry from the ignore list.

You can also use the ignore list to manually prevent Bastion from downloading those versions of repositories. This is very useful as it allows you to curate which versions of packages should not be available to all of your projects in a single place, rather than having to edit each of your projects individually.  

For example, if you find a bug in the nrk/predis project in version v0.6.6 you could add `nrk/predis/nrk_predis_v0.6.6` to the ignore list, delete the existing zip file and re-run Bastion.

It would then no longer be possible for any of your projects to either update to, or accidentally install the buggy version.

To make tracking what packages are available in Bastion easier, and find the exact spelling of the version tag, the packages with tag name attached are written to usingList.txt. So the easiest way to add something to the ignoreList.txt is to copy and paste it from usingList.txt. 


Example config file
~~~~~~~~~~~~~~~~~~~


.. code-block:: php
    :filename: bastionConfig.php

    <?php
    
    use Bastion\Config;

    $zipsDirectory = './zipsOutput';
    $githubAccessToken = '12345';
    $s3Key = '12345'
    $s3Secret = '54321'
    $s3Region = 'eu-west-1';
    $bucketName = 'satis.yourwebsite.com';

    $uploaderClass = 'Bastion\S3Sync';
    $restrictionClass = 'Bastion\S3ACLNoRestrictionGenerator';

    $repoList = [
        "aws/aws-sdk-php",
        "sebastianbergmann/exporter",
        "sebastianbergmann/phpunit",
        "sebastianbergmann/php-code-coverage",
        "sebastianbergmann/php-file-iterator",
        "sebastianbergmann/php-text-template",
        "sebastianbergmann/php-timer",
        "sebastianbergmann/phpunit-mock-objects",
        //and lots more...
    ];
    
    return new Config(
        $zipsDirectory, $dryRun, 
        $accessToken, $repoList, 
        $restrictionClass, $bucketName,
        $s3Key, $s3Secret,
        $s3Region,
        $uploaderClass
    );