#!/usr/local/bin/php
<?php
/**
 * Command line utility to find revisions by message, author within a date and time range
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

$short = 'hvcdb::m::a::p::s::t::u::';
$long  = array (
    'help',
    'verbose',
    'colored',
    'debug',
    'between::',
    'message::',
    'author::',
    'password::',
    'stop::',
    'target::',
    'user::',
);

$opts = getopt($short, $long);

if (isset($opts['h']) || isset($opts['help'])) {
    echo <<<HEDEDOC
Command line utility to find revisions by message, author within a date and time range

Usage:
    show-matched [OPTIONS]

    If environment variables SVN_TARGET, SVN_USERNAME, SVN_PASSWORD set and correspondent options not set
    then they will be used.

Options:
    -a, --author=PATTERN    commit author pattern (regex suported)
    -m, --message=PATTERN   pattern to filter commit message
    -s, --stop=REV          do not check revisions less than REV
    -b, --between=FROM~TO   datetime interval to match in php's DateTime constructor format
                            (see http://php.net/manual/en/datetime.formats.php for more info)

    -t, --target=TARGET     repository url, if not set current directory repo url will be used
                            (note, TARGE@REV also supported)
    -p, --password=PASSWORD         repository user password
    -u, --user=NAME         repository user name

    -h, --help              show this help
    -c, --colored           show colored output
    -v, --verbose           show additional output
    -d, --debug             show debug output

Output format:
    <line type> <revision> <author> <date and time> <comment>

    Line type may is a first character on each line and may be:
        !           - comment (only in verbose mode)
        #           - extra debug info (only in verbose mode when debug enabled)
        <no type>   - line with data

Pattern syntax:
    To enable regular expression usage PATTERN should starts with the slash character (/)

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

define ('AUTHOR', isset($opts['a']) && !empty($opts['a'])
    ? $opts['a']
    : (isset($opts['author']) && !empty($opts['author'])
        ? $opts['author']
        : '/.*/')
);

define ('MESSAGE', isset($opts['m']) && !empty($opts['m'])
    ? $opts['m']
    : (isset($opts['message']) && !empty($opts['message'])
        ? $opts['message']
        : '/.*/')
);

define ('BETWEEN', isset($opts['b']) && !empty($opts['b'])
    ? $opts['b']
    : (isset($opts['between']) && !empty($opts['between'])
        ? $opts['between']
        : 'today~now')
);

define ('STOP', isset($opts['s']) && (int)$opts['s'] > 0
    ? (int)$opts['s']
    : (isset($opts['stop']) && (int)$opts['stop']
        ? (int)$opts['stop']
        : '1')
);


define ('VERBOSE', isset($opts['v']) || isset($opts['verbose']));
define ('DEBUG', isset($opts['d']) || isset($opts['debug']));

define ('COLORED', posix_isatty(STDOUT) && isset($opts['c']) || isset($opts['colored']));

$shell = new SvnShell(USER, PASSWORD, TARGET);

$shell->showMatchedRevisions(MESSAGE, AUTHOR, STOP, BETWEEN);
