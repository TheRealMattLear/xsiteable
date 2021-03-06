<?php

/*
 * This class handles various aspects of access to whatever object deals with
 * this sort of stuff. So, for example, a widget needs to deal with who and how
 * of access, or perhaps a button plugin, or even a API call. Whatever.
 *
 */

namespace xs ;

class Access extends \xs\Core {

    function __construct () {
        // Go to parents constructor first, making this a xs_Core class
        parent::__construct() ;
    }

    function inject_access_rules ( $rules ) {

        $rules = array (

            array (

                'role' => 'user'

            )

        ) ;
    }

}
