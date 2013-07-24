<?php

namespace Iplant\Service\UserExporter;


abstract class AbstractUserExporter {

    public function __construct($event) {

    }

    public function export(\Entities\Person $person) {

    }
}
