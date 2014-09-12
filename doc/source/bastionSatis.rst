:tocdepth:
    1

Bastion Satis
=============

1) Get tags from Github

For each of the repositories listed in the repo list, get the list of tags for it from Github.

2) Download zipfiles

For each of the tags for each repo, download the zip archive of that version.

3) Modify zipfiles

Satis requires that the composer.json in the zip archive contains the semver version number. Most projects do not add this information, as it's far easier for the repository to manage it, so Bastion needs to add it to the composer.json.

4) Run satis

Once all of the zip files (or artifacts in Satis nomenclature) are ready, Bastion runs Satis internally to generate the Satis files.


5) Upload satis and zip file artifacts to S3

Once all the files are generated they are all uploaded to S3 or whatever storage you may have implemented.


First run is long
-----------------

The first run of Bastion will take ages and possibly run up against Githubs rate limit due to making too many calls. Additionally the uploading of all packages to S3 will also take ages.

Apart from the tags, which are downloaded each time, Bastion will not repeatedly download the same zipfiles, or upload them to S3. It is therefore safe to run it in 'batches', cancelling it when you get bored of it using all the bandwidth, and restarting it at a more appropriate time. 


*TODO* implement a cache for some of the requests, in particular getting the tags.