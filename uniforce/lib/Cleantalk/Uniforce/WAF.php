<?php


namespace Cleantalk\Uniforce;


class WAF extends UniforceModules
{

    public function __construct()
    {
        error_log('WebApplication FireWall class instantiated.');
    }

    public function get_module_statistics()
    {
        return 'WAF: statistics is empty.';
    }
}