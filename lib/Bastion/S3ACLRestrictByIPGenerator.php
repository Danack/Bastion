<?php


namespace Bastion;


class S3ACLRestrictByIPGenerator implements S3ACLGenerator {

    function __construct($allowedIPAddresses) {
        $this->allowedIPAddresses = $allowedIPAddresses;
    }
    
    function generateConditionBlock() {
        $generateCondition = function ($ipAddress) {
            return sprintf('"IpAddress": {
                                "aws:SourceIp": "%s"
                            }',
                           $ipAddress
            );
        };

        $conditions = array_map($generateCondition, $this->allowedIPAddresses);
        $allowCondition = implode(', ', $conditions);
        $allowCondition = '"Condition": {
            '.$allowCondition.'
          },';

        return $allowCondition;
    }
}

 