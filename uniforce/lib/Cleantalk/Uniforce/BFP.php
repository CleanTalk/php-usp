<?php


namespace Cleantalk\Uniforce;


class BFP extends UniforceModules
{

    /**
     * BFP constructor.
     */
    public function __construct()
    {
        error_log('BruteForse Protection class instantiated.');

    }

    function get_module_statistics()
    {
        return 'BFP: statistics is empty.';
    }

}