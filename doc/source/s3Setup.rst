Setting up S3 static satis DNS

Setup a bucket in your preferred region, with a name like satis.companyname.com

Setup a cname to point satis.companyname.com to satis.companyname.com.s3.amazonaws.com

And that's it. The access in this example is done via an ACL to allow certain IP address. If you have the capability I would suggest using an private virtual network to avoid the need for any over the internet access.