## Bastion


Two implementations of running a Satis server without using Packagist, with the capability of uploading the files to be uploaded to S3.


### Composer managed packages

Composer downloads and manages archiving of code from Github. Works great until you want to be able to inspect the packages, or have a library that has a non-ascii char in the project.




### Zips artifacts

Composers dependency resolver is not involved in any part of this and all dependencies need to be listed explicitly.

Slightly more work, but easier to manage exact downloads and doesn't have 'surprises'. When deploying code, I hate surprises.


