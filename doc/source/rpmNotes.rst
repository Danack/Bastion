:tocdepth:
    1

RPM notes
=========

RPM is not a difficult tool to use, but there are some useful things to know that are difficult to remember aka here are some crib notes.


.. rubric:: Install a package directly from a local rpm file

rpm -i sphinx-bootstrap-theme-0.4.0-1.noarch.rpm

.. rubric:: View contents of a package 

rpm -qlvp ../RPMS/i386/cdplayer-1.0-1.i386.rpm

.. rubric:: View the packages available in specific repos

yum --disablerepo=* --enablerepo=basereality list available
yum --disablerepo=* --enablerepo=basereality-noarch list available


.. rubric:: Check a spec file is valid

rpmlint imagick.spec


.. rubric:: Invoke the rpmbuild

rpmbuild -ba ~/rpmbuild/SPECS/autobugfix.spec


.. rubric:: Clean yum cache

yum clean all

This is very useful when debugging a new package, 



.. rubric:: Adding repo information to yum

Create a file in the /etc/yum.repos.d directory and it will be picked up automatically by yum.

.. code-block:: php
    :filename: /etc/yum.repos.d/basereality

    [basereality]
    name=BaseReality packages
    baseurl=http://rpm.basereality.com/basereality/RPMS/$basearch
    enabled=1
    gpgcheck=0
    
    
    [basereality-source]
    name=BaseReality packages
    baseurl=http://rpm.basereality.com/basereality/SRPM
    enabled=0
    gpgcheck=0



..rubric:: ACL for S3 static website

    {
        "Version": "2012-10-17",
        "Statement": [
            {
                "Effect": "Allow",
                "Action": [
                    "s3:GetBucketWebsite",
                    "s3:GetBucketAcl",
                    "s3:GetBucketPolicy",
                    "s3:GetBucketWebsite",
                    "s3:GetObject",
                    "s3:GetObjectAcl",
                    "s3:GetObjectVersion",
                    "s3:ListBucket",
                    "s3:ListMultipartUploadParts",
                    "s3:PutBucketAcl",
                    "s3:PutBucketPolicy",
                    "s3:PutObject",
                    "s3:PutObject",
                    "s3:PutObjectAcl",
                    "s3:PutObjectVersionAcl",
                    "s3:PutObjectAcl"
                ],
                "Resource": [
                    "arn:aws:s3:::www.bastionrpm.com"
                ]
            }
        ]
    }

