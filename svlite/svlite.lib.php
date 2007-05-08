<?php
//
// $Id: svlite.lib.php,v 1.3 2007/05/08 02:35:14 zaurum Exp $
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
// Revision 1.3  2007/05/08 02:35:14  zaurum
// Basic functionality
//
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
//
// Get control data fron .ini file (derived from script filename by replacing the extension with .ini)
//
    svlite_internal_get_control_data();
//
// MVC initialization
//
    svlite_internal_init_mvc();
//
// Call controller and perform requrieed action
//
    svlite_internal_controller();
}  

/**
 * SVLite shutdown code
 */
function svlite_epilog() {
    global $sv;

    foreach ($sv['exit'] as $f) {
        if (function_exists($f)) {
            $f();
        }
    }
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
// errors: registered errors
        'errors' => array(),
// exit: epilog (shutdown) functions
        'exit' => array(),
        'func' => array(),
        'get' => array(),
        'ini' => array(),
        'path' => array(),
        'post' => array(),
        'system' => array(),
        'vars' => array(),
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
// Populate system vars
    $sv['system'] = &$_SERVER;
// Obtain GET/POST vars (with magic quotes removed)
    foreach (array_keys($_GET) as $k) {
        $sv['get'][$k] = svlite_get_parameter($k, 'GET');
    }
    foreach (array_keys($_POST) as $k) {
        $sv['post'][$k] = $this->get_parameter($k, 'POST');
    }
    $sv['system']['method'] = strtolower($_SERVER["REQUEST_METHOD"]);
// 'request' will refer to the actual request data
    $sv['request'] =& $sv[$sv['system']['method']];
}

/**
 * Obtains the user-passed value w/magic quotes removed
 * 
 * @param $v  name of the parameter
 * @param optional $src - source ('GET' or 'POST')    
 */ 
function svlite_get_parameter($v, $src = '') {
    if (!is_string($src)) $src = '';
    if (!in_array($src, array('GET','POST'))) $src = $_SERVER["REQUEST_METHOD"];
    ($src == 'POST') ? $vv =& $_POST[$v] : $vv =& $_GET[$v]; 
    if (ini_get('magic_quotes_gpc')) {
        if (is_array($vv)) {
            return array_map('stripslashes', $vv);
        } else {
            return stripslashes($vv);
        }
    } else {
        return $vv;
    }
}

function svlite_is_empty($s) {
    return (strval($s) == '');
}

function svlite_set_var_if_empty(&$arr, $k, $v) {
    if (!svlite_is_empty($k)) {
        if ((!isset($arr[$k])) || (svlite_is_empty($arr[$k]))) {
            $arr[$k] = $v;
        }
    }
}

function svlite_internal_get_control_data() {
    global $sv;

// Take basename of the script and derive .ini file name from it
    $scriptname = $_SERVER['SCRIPT_FILENAME'];
    $extpos = strrpos($scriptname, '.');
    if (false !== $extpos) {
        $inifile = substr($scriptname, 0, 1 + $extpos) . "ini";
        if (is_file($inifile)) {
            $sv['ini'] = @parse_ini_file($inifile);
        }
    }
// Apply defaults
    svlite_set_var_if_empty($sv['ini'], 'action_infix', 'action');
    svlite_set_var_if_empty($sv['ini'], 'action_var', 'action');
    svlite_set_var_if_empty($sv['ini'], 'default_action', 'default');
    svlite_set_var_if_empty($sv['ini'], 'dbh_infix', 'dbh');
    svlite_set_var_if_empty($sv['ini'], 'filter_infix', 'filter');
    svlite_set_var_if_empty($sv['ini'], 'session_infix', 'session');
    svlite_set_var_if_empty($sv['ini'], 'sv_prefix', 'svlite');
// Derive action handler name pattern
    $sv['func']['action_handler'] = $sv['ini']['sv_prefix'] . '_' . $sv['ini']['action_infix'] . '_%s'; 
}

function svlite_internal_init_mvc() {
    global $sv;

// If no output buffering active, enable it
    if (count(ob_get_status(true)) <= 1) {
        ob_start();
    }
// Scan function namespace for filters and run filters
}

function svlite_internal_register_error($errcode, $errstr) {
    global $sv;

    $sv['errors'][] = array($errcode, $errstr);
}

function svlite_internal_controller() {
    global $sv;

// Get action name
    $actvar = $sv['ini']['action_var'];
    if ((!isset($sv['request'][$actvar])) || (!is_string($sv['request'][$actvar]))) {
        $actval = $sv['ini']['default_action'];
    } else {
        $actval = $sv['request'][$actvar];
    }
// Store action name for possible later use
    $sv['vars']['action'] = $actval; 
// Get action handler name
    $af = sprintf($sv['func']['action_handler'], $actval);
// Run action handler name
    if (function_exists($af)) {
        $af();
    } else {
        svlite_internal_register_error(0, "Action handler $af not found");
    }
}

/**
 * Checks inout (GET/POST) data against regexp patterns
 * 
 * @param $arr  reference of array with regexp definitions
 *     expected structure of an entry:
 *     'actionname' => array()
 *         'get' => array(
 *             'parmname' => array('flags', 'regexp')
 *             ...  
 *         ), 
 *         'post' => array(
 *             'parmname' => array('flags', 'regexp')
 *             ...  
 *         ), 
 *     }
 *     if either of 'get'/'post' is missing, that method is
 *     unavailable (error)
 * @param $a   action name (key to the above array entry)
 * @param $purge default true  whether to remove the input parameter
 *     missing from definitions from the request vars (prevent input
 *     poisoning) 
 * 
 * @returns
 *     Array with found parameters mismatch
 *     true if no errors
 *     false if wrong method or data structure error        
 */ 
function svlite_check_input(&$arr, $a, $purge = true) {
// Parameters check

}

//
// View calls
//
?>
