#!/usr/local/bin/php
<?php
/**
 * Command line utility to see affected files by specific revisions
 *
 *
 * Copyright (c) 2012 Ben Pinepain <pinepain@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

set_time_limit(15 * 60); // 15 minutes
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'autoloader.php';


$short = 'hvcdr::t::u::p::';
$long  = array (
    'help',
    'verbose',
    'colored',
    'debug',
    'revisions::',
    'target::',
    'user::',
    'password::',
);

$opts = getopt($short, $long);

if ((!isset($opts['r']) || empty($opts['r'])) && (!isset($opts['revisions']) || empty($opts['revisions']))) {
    $opts['r'] = stream_get_contents(fopen("php://stdin", "r"));
    $opts['r'] = preg_replace('/\s+/', ',', $opts['r']);
}

if (isset($opts['h']) || isset($opts['help'])) {
    echo <<<HEDEDOC
Command line utility to see affected files by specific revisions

Usage:
    show-matched [OPTIONS]

    If environment variables SVN_TARGET, SVN_USERNAME, SVN_PASSWORD set and correspondent options not set
    then they will be used.

    Example:
        show-matched -utest -ptest -r124,125,130:150,140,180:HEAD,HEAD:170 -tsvn+ssh://svn.example.com/branches/test
        show-matched -utest -ptest -r124,125,130:150,180:HEAD -tsvn+ssh://svn.example.com/branches/test@HEAD

        both are doing the same, while overlapped revision numbers are merged in a favor more complex range

Options:
    -r, --revisions=REVS    revisions list to check, if not REVS given read them from STDIN (items on separate lines
                            will be glued with comma ',')
    -t, --target=TARGET     repository url, if not set current directory repo url will be used
                            (note, TARGE@REV also supported)
    -u, --user=NAME         repository user name
    -p, --password=PASSWORD         repository user password

    -h, --help              show this help
    -c, --colored           show colored output
    -v, --verbose           show additional output
    -d, --debug             show debug output

Input format:
    TARGET  <url or path>[@<revision>]
    REVS    Comma-separated *single revision numbers* or/and *colon-separated ranges*. HEAD will be transformed to the
            latest TARGET revision or number, all non-numeric characters excluding comma (,) and colon (:) removed.

Output format:
    <line type> <revision> <author> <path to file/directory inside revision root>

    Line type is a first character on each line and may be:
        !   - comment (only in verbose mode)
        #   - extra debug info (only in verbose mode when debug enabled)
        A   - file/directory was added
        D   - file/directory was deleted
        M   - file/directory was modified
        R   - file/directory was replaced

Copyright (c) 2012 Ben Pinepain <pinepain@gmail.com>

HEDEDOC;
    exit();
}

define ('TARGET', isset($opts['t']) && !empty($opts['t'])
    ? $opts['t']
    : (isset($opts['target']) && !empty($opts['target'])
        ? $opts['target']
        : getenv('SVN_TARGET'))
);

define ('USER', isset($opts['u']) && !empty($opts['u'])
    ? $opts['u']
    : (isset($opts['user']) && !empty($opts['user'])
        ? $opts['user']
        : getenv('SVN_USERNAME'))
);

define ('PASSWORD', isset($opts['p']) && !empty($opts['p'])
    ? $opts['p']
    : (isset($opts['password']) && !empty($opts['password'])
        ? $opts['password']
        : getenv('SVN_PASSWORD'))
);

define ('VERBOSE', isset($opts['v']) || isset($opts['verbose']));
define ('DEBUG', isset($opts['d']) || isset($opts['debug']));

define ('COLORED', posix_isatty(STDOUT) && isset($opts['c']) || isset($opts['colored']));

$shell = new \SvnShell(USER, PASSWORD, TARGET);

$revs = $shell->getRevisionsListFromString(isset($opts['r']) ? $opts['r'] : $opts['revisions']);

$shell->showAffectedFilesInRevisionsList($revs);
