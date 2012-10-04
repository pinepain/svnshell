#!/usr/bin/php
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


$help = <<<HEDEDOC
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
    -f, --format=COLUMNS    columns to be shown in output, default - all columns, for empty COLUN 'R' will be used
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
    COLUMNS comma-separated column names which are listed below. Unknown columns are ignored
            T - line type (has no affect for extra information and debug output)
            R - revision number
            A - author
            P - path under revision root  (default for empty COLUMNS value)

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

$shell = new SvnShell('cdfhprtuv', array('f'=> 'P', 't' => '.'), $help);


$shell->showAffectedFilesInRevisions();
