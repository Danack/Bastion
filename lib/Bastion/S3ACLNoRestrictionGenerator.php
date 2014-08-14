<?php


namespace Bastion;


class S3ACLNoRestrictionGenerator implements S3ACLGenerator {
    function generateConditionBlock() {
        return '';
    }
}

 