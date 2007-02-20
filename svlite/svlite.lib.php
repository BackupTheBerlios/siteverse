<?php
//
// $Id: svlite.lib.php,v 1.2 2007/02/20 01:38:50 zaurum Exp $
// Author: Konstantin Boyandin <konstantin@boyandin.ru>
//
// This file is a part of SVLite MVC framework distribution
// http://siteverse.com
//
// svlit.lib.php: main SVLite library file (PHP 4.3.0+ compatible)
// Copyright (c) 2005,2006,2007 Konstantin Boyandin. All rights reserved.
//
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public
// License along with this library; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
//
// $Log: svlite.lib.php,v $
// Revision 1.2  2007/02/20 01:38:50  zaurum
// 2007-02-20 1
//
// Revision 1.1  2007/02/18 05:51:19  zaurum
// Stub library file and license information added
//
//

svlite_prolog();

//
// Public SVLite library functions
//

/** 
 * SVLite startup code
 */
function svlite_prolog() {
//
// Register shutdown sequence
//
    register_shutdown_function('svlite_epilog');
//
// register_globals are disabled, make sure we handle it anyway
//
    svlite_internal_purge_globals();
//
// Set up internal data. We use the only global var, $sv
//
    svlite_internal_set_vars();
}  

/**
 * SVLite shutdown code
 */
function svlite_epilog() {
}

//
// Private SVLite functions. Do not call directly from your
// applciation unless you know what you are doing
//

/**
 * Unset all the globals save superglobals
 */ 
function svlite_internal_purge_globals() {
    if (ini_get('register_globals')) {
        $keep = array('_ENV' => 1, '_GET' => 1, '_POST' => 1, '_COOKIE' => 1, '_FILES' => 1, '_SERVER' => 1, '_REQUEST' => 1, 'GLOBALS' => 1);
        foreach ($GLOBALS as $k => $v) {
            if (!isset($keep[$k])) unset($GLOBALS[$k]);
        }
    }
}

/**
 * Set up internal data storage and SV 
 */ 
function svlite_internal_set_vars() {
    global $sv;

// Initialize storage sections    
    $sv = array(
        'path' => array()
    );
// Set up paths
    $sv['path']['sv'] = dirname(__FILE__) . DIRECTORY_SEPARATOR;
    $sv['path']['base'] = getcwd() . DIRECTORY_SEPARATOR;
// Add both to include_path, SV base path first
    ini_set('include_path',
        $sv['path']['sv'] . PATH_SEPARATOR .
        $sv['path']['base'] . PATH_SEPARATOR .
        ini_get('include_path')
    );
}
?>
