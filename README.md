## Bastion


Run your own Packagist server, so that you can do Composer update locally. Oh and it also create RPM packages for your applications.


### Setup satis repo

* Run ./src/bastion.php It will ask you to provide a Github access token

* Edit bastionConfig.php to list the Github repos you want to be available to install.

* Run ./src/bastion.php again. Now that everything is stup it will download the tagged versions of the libraries as zip balls, before uploading them to S3.

You will now have a satis repository uploaded to S3. You can also run this satis provider locally, with the PHP builtin server `php -S localhost:8000 -t zipsOutput/` .



### Setting up S3 static satis DNS 

* Setup a bucket in your preferred region, with a name like satis.companyname.com

* Setup a cname to point satis.companyname.com to satis.companyname.com.s3.amazonaws.com

And that's it. The access in this example is done via an ACL to allow certain IP address. If you have the capability I would suggest using an private virtual network to avoid the need for any over the internet access.



### Setting up RPM packager

*Insert instruction here*