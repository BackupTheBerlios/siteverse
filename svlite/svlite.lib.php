<?php
//
// $Id: svlite.lib.php,v 1.5 2007/05/16 09:03:24 zaurum Exp $
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
// Revision 1.5  2007/05/16 09:03:24  zaurum
// Added redirection
//
// Revision 1.4  2007/05/14 12:27:16  zaurum
// Extended functionality, basic View and Controller
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
// Set up internal data. We use the only global var, $sv
//
    svlite_internal_set_vars();
//
// register_globals are disabled, make sure we handle it anyway
//
    svlite_internal_purge_globals();
//
// Get control data fron .ini file (derived from script filename by replacing the extension with .ini)
//
    svlite_internal_get_control_data();
//
// MVC initialization
//
    svlite_internal_init_mvc();
}  

function svlite_process() {
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
    global $sv;

    if (ini_get('register_globals')) {
// If not in 'keep' (precious) array, purge    
        foreach ($GLOBALS as $k => $v) {
            if (!in_array($sv['keep'][$k])) unset($GLOBALS[$k]);
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
        'headers' => array(),
        'ini' => array(),
        'path' => array(),
        'post' => array(),
        'system' => array(),
        'vars' => array(),
        'keep' => array('sv', '_ENV', '_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_REQUEST', 'GLOBALS')
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
        $sv['post'][$k] = svlite_get_parameter($k, 'POST');
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
// Validate paths
    if (isset($sv['ini']['templates_dir']) && is_string($sv['ini']['templates_dir'])) {
        $sv['ini']['templates_dir'] = svlite_check_path($sv['ini']['templates_dir']);
    }
// Apply defaults
    svlite_set_var_if_empty($sv['ini'], 'action_infix', 'action');
    svlite_set_var_if_empty($sv['ini'], 'action_var', 'action');
    svlite_set_var_if_empty($sv['ini'], 'content_type', 'text/html; charset=utf-8');
    svlite_set_var_if_empty($sv['ini'], 'default_action', 'default');
    svlite_set_var_if_empty($sv['ini'], 'dbh_infix', 'dbh');
    svlite_set_var_if_empty($sv['ini'], 'filter_infix', 'filter');
    svlite_set_var_if_empty($sv['ini'], 'session_infix', 'session');
    svlite_set_var_if_empty($sv['ini'], 'session_name', 'SVLITE');
    svlite_set_var_if_empty($sv['ini'], 'sv_prefix', 'svlite');
    svlite_set_var_if_empty($sv['ini'], 'templates_dir', 'templates' . DIRECTORY_SEPARATOR);
    svlite_set_var_if_empty($sv['ini'], 'use_compression', false);
    svlite_set_var_if_empty($sv['ini'], 'use_session', true);
    svlite_set_var_if_empty($sv['ini'], 'view_infix', 'view');
// Define list of 'precious' names
    if ((isset($sv['ini']['precious'])) && (!svlite_is_empty($sv['ini']['precious']))) {
        $sv['keep'] = array_unique(array_merge($sv['keep'], split('[ ]*,[ ]*',$sv['ini']['precious'])));
    }
// Derive action handler name pattern
    $sv['func']['action_handler'] = $sv['ini']['sv_prefix'] . '_' . $sv['ini']['action_infix'] . '_%s'; 
    $sv['func']['view_handler'] = $sv['ini']['sv_prefix'] . '_' . $sv['ini']['view_infix'] . '_%s_%s'; 
}

/**
 * Checks path:
 *   - adds closing directory separator
 *   - checks for relative components; if present, returns empty string  
 */ 
function svlite_check_path($path) {
    if (isset($path) && is_string($path)) {
// Canonize slashes
        $path = svlite_unify_dir_separators($path);
// Add terminating slash
        if (substr($path, -1) != DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
// No relative paths allowed
            if (svlite_is_relative_path($path)) {
                $path = '';
            }
        }
    }
    return $path;
}

function svlite_unify_dir_separators($str) {
    if (isset($str) && is_string($str)) {
        return str_replace(
            array("/", "\\"),
            array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR),
            $str
        );
    } else
        return '';
}

function svlite_internal_init_mvc() {
    global $sv;

// If no output buffering active, enable it
    if (count(ob_get_status(true)) <= 1) {
        ob_start();
    }
// Scan function namespace for filters and run filters
// Start session
    if ($sv['ini']['use_session']) {
        session_name($sv['ini']['session_name']);
        session_start();
    }
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
 * Returns true if the string contains relative path specification
 */ 
function svlite_is_relative_path($path) {
    if (isset($path) && is_string($path)) {
        $rel = '..' . DIRECTORY_SEPARATOR;
        return (false !== strstr($path, $rel));
    } else
        return false;
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
 *     'flags' is a string;
 *        if contains 'A', array values are allowed
 *     'regexp' is a regexp used to match  
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
    global $sv;
    $rc = true;

// Parameters check
    if (!is_array($arr)) {
        svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ': not an array');
        return false;
    }
// If such an action is defined, scan parameters
    if (isset($arr[$a]) && (is_array($arr[$a]))) {
        $method =& $sv['system']['method'];
        if (isset($arr[$a][$method]) && (is_array($arr[$a][$method]))) {
            $cdata =& $arr[$a][$method];
            $rc = array();
            foreach (array_keys($sv['request']) as $k) {
// $k is var name, $v its value
                $v =& $sv['request'][$k];
                if (isset($cdata[$k]) && is_array($cdata[$k])) {
// Match parameter against the regexp
                    $marr =& $cdata[$k];
                    $arrayallowed = (false !== strstr($marr[0], 'A'));
                    if (is_array($v)) {
                        if ($arrayallowed) {
// Match every element of the array
                            foreach (array_keys($v) as $kv) {
                                if (!preg_match($marr[1], $v[$kv])) {
                                    svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ": value of '$k':$kv is invalid");
                                    $rc[$k] = "invalid value at $kv";
                                }
                            }
                        } else {
// Array not allowed - ad error record
                            svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ": parameter '$k' may not be an array");
                            $rc[$k] = 'array not allowed';
                        }                                   
                    } else {
// Check the single value
                        if (!preg_match($marr[1], $v)) {
                            svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ": value of '$k' is invalid");
                            $rc[$k] = 'invalid value';
                        }
                    }
                } else {
// Var missing from definition, purge it if required
                    if ($purge) {
                        $v = NULL;
                        unset($v);
                        unset($sv['request'][$k]);
                    }
                }
            }
// All checks passed
            if (count($rc) <= 0) {
                $rc = true;
            }
            return $rc;
// Method exists, now scan the request data
        } else {
            svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ": no data for method '$method' of the action '$a'");
            return false;
        } 
    } else {
        svlite_internal_register_error(0, 'svlite_check_input' . ' ' . __LINE__ . ": no data for the action '$a'");
        return false;
    }
}

/**
 * Redirects browser to a new page
 * 
 * @param $address URL or URI to redirect to  
 */ 
function svlite_http_redirect($address = "/") {
    $https = false;
    $stdport = true;
    $srvport = $_SERVER['SERVER_PORT'];
    $rc = "";
    if (!preg_match("#^[a-z]+://#", $address)) {
// http? https?
        if (isset($_SERVER['SSL_PROTOCOL'])) {
            $https = true;
            if ($srvport != '443') {
                $stdport = false;
            }
        } else {
            if ($srvport != '80') {
                $stdport = false;
            }
        }
// Construct the URL
        if ($https) {
            $rc = "https://";
        } else {
            $rc = "http://";
        }
        $rc .= $_SERVER['SERVER_NAME'];
        if (!$stdport) {
            $rc .= ":$srvport";
        }
        if (substr($address, 0, 1) != "/") {
            $rc .= "/";
        }
        $address = $rc . $address;
    }
    header("Location: $address");
    ob_end_clean();
    exit(0);
}


//
// View calls: simple template engine
//
///////////////////////////////////// begin

/**
 * Assigns value to a template variable
 *  
 * @param $k  name of the variable
 * @param 4v value to assign
 */ 
function svlite_view_simple_assign($k, $v) {
    global $sv;

    if (isset($k) && isset($v)) {
        if (!svlite_is_empty($k)) {
            if (!isset($sv['vars'][$k]))
                $sv['vars'][$k] = $v;
        }
    }
}

/**
 * Takes file from a template directory and processes it 
 *  
 * @param $tmplname name of the template file in
 *   $sv['ini']['templates_dir']
 * @returns processed template
 */ 
function svlite_view_simple_fetch($tmplname) {
    global $sv;

    if (isset($tmplname) && is_string($tmplname)) {
// Sanity check
        if (svlite_is_relative_path($tmplname)) {
            svlite_internal_register_error(0, 'svlite_view_simple_fetch' . ' ' . __LINE__ . ": template name may not be relative");
            return false;
        }
// Construct name
        $fullname = svlite_unify_dir_separators(
            $sv['path']['base'] . $sv['ini']['templates_dir'] . $tmplname
        );
// If file exists, process it
        if (file_exists($fullname)) {
            ob_start();
            extract($sv['vars'], EXTR_SKIP);
            require($fullname);
            return ob_get_clean();
        } else {
            svlite_internal_register_error(0, 'svlite_view_simple_fetch' . ' ' . __LINE__ . ": template $tmplname does not exist");
            return '';
        }
    } else
        return false; 
}

/**
 * Takes file from a template directory, processes it and
 * sends to browser  
 *  
 * @param $tmplname name of the template file in
 *   $sv['ini']['templates_dir']
 */ 
function svlite_view_simple_display($tmplname) {
// Send headers, if any
    svlite_view_simple_sendheaders();
// Process and send template
    if (isset($tmplname) && is_string($tmplname)) {
        echo svlite_view_simple_fetch($tmplname);
    }
}

/**
 * Sets header to be sent from 'display' view call or similar call
 * 
 * @param $k header name
 * @param $v header value   
 */ 
function svlite_view_simple_setheader($k, $v) {
    global $sv;

    if (isset($k) && isset($k) && is_string($k) && is_string($v)) {
        $sv['headers'][$k] = $v;
    }
}

/**
 * Sends HTTP headers accumulated so far
 */ 
function svlite_view_simple_sendheaders() {
    global $sv;

    if (!isset($sv['headers']['Content-Type']) || !is_string($sv['headers']['Content-Type'])) {
        $sv['headers']['Content-Type'] = $sv['ini']['content_type'];
    }
    foreach ($sv['headers'] as $k => $v) {
        header("$k: $v");
    }
// Reset headers array
    $sv['headers'] = array();
}

///////////////////////////////////// end
?>
