

Setting up S3
=============

If you want to deploy the Satis repository Bastion builds for you, you will need to setup S3 to serve a statis website. To do this you will need to:


1. Create a bucket in your preferred region, with a name like 'satis.companyname.com'. The bucket region cannot be changed after it is created (without destroying the bucket first) so choose wisely. 


2. In you DNS management tools for your domain name, setup a cname to point satis.companyname.com to satis.companyname.com.s3.amazonaws.com


And that's it. 

When Bastion runs it generated an `ACL list <http://docs.aws.amazon.com/AmazonS3/latest/dev/acl-overview.html>`_ to limit who can access your repository. However, if you require complete security and protection of your code, I would suggest setting up a virtual private network between S3 and your machines that need access to your repository.


