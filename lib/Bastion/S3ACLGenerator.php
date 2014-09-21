<?php


namespace Bastion;


interface S3ACLGenerator {

    /**
     * Generate the approprate string to be used in a S3::putBucketPolicy call
     * @return string
     */
    function generateConditionBlock();
}

//$acp = AcpBuilder::newInstance()
//    ->setOwner($myOwnerId)
//    ->addGrantForEmail('READ', 'test@example.com')
//    ->addGrantForUser('FULL_CONTROL', 'user-id')
//    ->addGrantForGroup('READ', Group::AUTHENTICATED_USERS)
//    ->build();