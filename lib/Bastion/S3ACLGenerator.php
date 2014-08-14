<?php


namespace Bastion;


interface S3ACLGenerator {

    /**
     * Generate the approprate string to be used in a S3::putBucketPolicy call
     * @return string
     */
    function generateConditionBlock();
} 