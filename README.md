## Bastion


Two implementations of running a Satis server without using Packagist, with the capability of uploading the files to be uploaded to S3.


### Composer managed packages

Composer downloads and manages archiving of code from Github. Works great until you want to be able to inspect the packages, or have a library that has a non-ascii char in the project.

 
### Zips artifacts

Composers dependency resolver is not involved in any part of this and all dependencies need to be listed explicitly.

Slightly more work, but easier to manage exact downloads and doesn't have 'surprises'. When deploying code, I hate surprises.



### Setup

* Copy the files from `copy-below-root` to the directory above Bastion.

* Put your AWS key and secret in ../config.php

Either

* List the repositories that you want to use in satis-public.json if you want Composer to download the files, and also grab the dependencies.

* Run `runSatis.sh`

Or

* List the Github repository names in repos.config.php if you want to have Bastion download the zip files, and manually list the dependencies yourself.

* Run `runArtifact.sh`



You will now have a satis repository uploaded to S3. You can also run this satis provider locally, with the PHP builtin server `php -S localhost:8000 -t zipsOutput/` which allows you to test the repo by doing an update locally if you wish to.



### Setting up S3 static satis DNS 

* Setup a bucket in your preferred region, with a name like satis.companyname.com

* Setup a cname to point satis.companyname.com to satis.companyname.com.s3.amazonaws.com

And that's it. The access in this example is done via an ACL to allow certain IP address. If you have the capability I would suggest using an private virtual network to avoid the need for any over the internet access.