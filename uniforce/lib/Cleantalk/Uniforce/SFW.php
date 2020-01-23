<?php

namespace Cleantalk\Uniforce;

class SFW extends UniforceModules
{

    public function __construct()
    {
        error_log('Security FireWall class instantiated.');
    }

    public function get_module_statistics()
    {
        return 'SFW: statistics is empty.';
    }

}