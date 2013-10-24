Features that Subversion SVN client should have had from the cradle

TODO: add docs, use cases, some examples

P.S.: it was written in big hurry, so if something is wrong or you have question, comments, feature request
or something else - just email me or create a bug report/feature or even pull request.

## Setup:

```bash
git clone git@github.com:pinepain/svnshell.git
cd svnshell

alias svn-show-affected="php `pwd`/show-affected.php"
alias svn-show-matched="php `pwd`/show-matched.php"
```

## Usage:

### svn-show-affected

```
$ svn-show-affected -h
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
            K - path kind ('file' or 'dir'), used only with P column.
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

Copyright (c) 2013 Bogdan Padalko <zaq178miami@gmail.com>
```

### svn-show-matched

```
$ svn-show-matched -h
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
```
