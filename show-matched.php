#!/usr/bin/php
<?php
/**
 * Command line utility to find revisions by message, author within a date and time range
 *
 *
 * Copyright (c) 2013 Bogdan Padalko <zaq178miami@gmail.com>
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

$help = <<<HEDEDOC
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
    -f, --format=COLUMNS    columns to be shown in output, default - all columns, for empty COLUN 'R' will be used

    -t, --target=TARGET     repository url, if not set current directory repo url will be used
                            (note, TARGE@REV also supported)
    -p, --password=PASSWORD         repository user password
    -u, --user=NAME         repository user name

    -h, --help              show this help
    -c, --colored           show colored output
    -v, --verbose           show additional output
    -d, --debug             show debug output

Input format:
    COLUMNS comma-separated column names which are listed below. Unknown columns are ignored
            T - line type (has no affect for extra information and debug output)
            R - revision number (default for empty COLUMNS value)
            A - author
            D - date and time
            C - revision comment

    TARGET  <url or path>[@<revision>]

Output format:

    <line type> <revision> <author> <date and time> <revision comment>

    Line type may is a first character on each line and may be:
        !           - comment (only in verbose mode)
        #           - extra debug info (only in verbose mode when debug enabled)
        r           - line with data

Pattern syntax:
    To enable regular expression usage PATTERN should starts with the slash character (/)

Copyright (c) 2013 Bogdan Padalko <zaq178miami@gmail.com>

HEDEDOC;

$shell = new SvnShell('habcdfmpstuv',
                      array('f' => 'R',
                            'a' => '/.*/',
                            'm' => '/.*/',
                            'b' => 'today~now',
                            's' => 1,
                            't' => '.',
                      ),
                      $help);

$shell->showMatchedRevisions();
