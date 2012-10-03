<?php
/**
 * A PHP class to do implement features that Subversion SVN client should have had from the cradle
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

class SvnShell {
    private $c;

    private $info = array ();

    private $colors = array (
        'A'            => 'green',
        'D'            => 'red',
        'M'            => 'blue',
        'R'            => 'cyan',
        '!'            => 'dark',
        'line_type'    => 'yellow',
        'found_rev'    => 'red',
        'found_author' => 'green',
        'found_date'   => 'blue',
        'found_msg'    => 'white',
        'err'          => 'bold_red',
        'debug'        => 'yellow',
    );

    private $opts = array (
        'a' => array (
            'name' => 'author',
            'val'  => '::'
        ),
        'b' => array (
            'name' => 'between',
            'val'  => '::'
        ),
        'c' => array (
            'name' => 'colored',
            'val'  => ''
        ),
        'd' => array (
            'name' => 'debug',
            'val'  => ''
        ),
        'f' => array (
            'name' => 'format',
            'val'  => '::'
        ),
        'h' => array (
            'name' => 'help',
            'val'  => ''
        ),
        'm' => array (
            'name' => 'message',
            'val'  => '::'
        ),
        'p' => array (
            'name' => 'password',
            'val'  => '::'
        ),
        'r' => array (
            'name' => 'revisions',
            'val'  => '::'),
        's' => array (
            'name' => 'stop',
            'val'  => '::'),
        't' => array (
            'name' => 'target',
            'val'  => '::'),
        'u' => array (
            'name' => 'user',
            'val'  => '::'
        ),
        'v' => array (
            'name' => 'verbose',
            'val'  => ''
        ),
    );


    public function __construct($used_opts, array $defaults = array (), $help = '') {
        $this->initConfig($used_opts, $defaults);

        if ($this->getCfg('help')) {
            echo $help;
            exit(0);
        }

        $repo = empty($repo) ? '.' : $repo;

        if ($this->getCfg('colored')) {
            $this->c = new ANSIColor();
        }

        // get info about subversion copy
        $command = 'svn info --xml --non-interactive --no-auth-cache';

        if ($this->getCfg('username')) {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $this->getCfg('username'));
            $command .= ' --username ' . $this->getCfg('username');
        }

        if ($this->getCfg('password')) {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $this->getCfg('password'));
            $command .= ' --password ' . $this->getCfg('password');
        }

        $command .= ' ' . $repo;
        $this->printDebug("# username: " . $this->getCfg('username') . "  password: " . $this->getCfg('password') . "  target: " . $this->getCfg('target'));
        $this->printDebug("# command: $command");

        $output = shell_exec($command);

        $parser = xml_parser_create();

        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $output, $values, $tags);
        xml_parser_free($parser);

        if (!isset($tags['url'])) {
            $this->formatString("Could not get repository info", 'err');
            die();
        }

        $this->info = array (
            'url'      => $values[$tags['url'][0]]['value'],
            'root'     => $values[$tags['root'][0]]['value'],
            'revision' => $values[$tags['commit'][0]]['attributes']['revision'],
        );

        $this->info['prefix'] = str_replace($this->info['root'], '', $this->info['url']);

        if ($this->getCfg('verbose')) {
            echo $this->formatString('! ' . $this->info['url'] . '@' . $this->info['revision'], '!'), "\n";
        }
    }

    public function showMatchedRevisions() {
        $between  = $this->getDateTimeIntervalFromString($this->getCfg('between'));
        $stop_rev = $this->getRevisionsListFromString($this->getCfg('stop'));
        $stop_rev = array_shift($stop_rev);
        $author   = $this->getCfg('author');
        $message  = $this->getCfg('message');


        $start_rev = $this->info['revision'];

        if ($stop_rev > $start_rev) {
            $this->formatString('Invalid revision number', 'err');
        }

        if ($this->getCfg('verbose')) {
            $str = '! from: ' . str_pad($start_rev, 8, ' ', STR_PAD_RIGHT) . ' to: ' . str_pad($stop_rev, 8, ' ', STR_PAD_RIGHT)
                . '  between: ' . $between[0]->format(DateTime::RFC1036) . ' and: ' . $between[1]->format(DateTime::RFC1036)
                . ' message: ' . $message . ' author: ' . $author;
            echo $this->formatString($str, '!'), "\n";
        }

        $revs = $this->getRevisionsListFromString($stop_rev . ':' . $start_rev);

        $revs = array_reverse($revs);

        $found_revs_count = 0;

        foreach ($revs as $rev) {
            $this->printDebug('# check ' . $rev . ': ', false);

            $info = $this->getRevisionLog($rev);


            if (!$info) {
                $this->printDebug('no commits under given url in this revision');
                continue;
            }

            $info['date'] = new DateTime($info['date']);

            if ($info['date'] > $between[1]) {
                $this->printDebug('date ' . $info['date']->format(DateTime::RFC1036) . ' greater than end date ' . $between[1]->format(DateTime::RFC1036));
                continue;
            }

            if ($info['date'] < $between[0]) {
                $this->printDebug('date ' . $info['date']->format(DateTime::RFC1036) . ' less than start date ' . $between[0]->format(DateTime::RFC1036));
                break;
            }


            if ('/' == $author[0]) {
                $m_author = preg_match($author, $info['author']);
            } else {
                $m_author = $author == $info['author'];
            }

            if (!$m_author) {
                $this->printDebug('author "' . $info['author'] . '" does not match pattern ' . $author);
                continue;
            }

            $info['msg'] = trim(preg_replace('/\s+/', ' ', $info['msg']));

            if ('/' == $message[0]) {
                $m_message = preg_match($message, $info['msg']);
            } else {
                $m_message = $message == $info['msg'];
            }

            if (!$m_message) {
                $this->printDebug('message "' . $info['msg'] . '" does not match pattern ' . $message);
                continue;
            }

            $this->printDebug('match found');

            $info['date'] = $info['date']->format(DateTime::RFC1036);

            $format = $this->getCfg('format');

            $output = array ();

            if (in_array('T', $format)) {
                $output[] = $this->formatString('r', 'line_type');
            }

            if (in_array('R', $format)) {
                $output[] = $this->formatString(str_pad($info['rev'], 7, ' ', STR_PAD_LEFT), 'found_rev');
            }

            if (in_array('A', $format)) {
                $output[] = $this->formatString(str_pad($info['author'], 7, ' ', STR_PAD_LEFT), 'found_author');
            }

            if (in_array('D', $format)) {
                $output[] = $this->formatString($info['date'], 'found_date');
            }

            if (in_array('C', $format)) {
                $output[] = $this->formatString($info['msg'], 'found_msg');
                ;
            }

            $found_revs_count ++;

            if (!empty($output)) {
                echo join('  ', $output), "\n";
            }
        }

//        if ($this->getCfg('verbose')) {
            echo $this->formatString("! $found_revs_count revisions matched", '!'), "\n";
//        }

    }

    public function showAffectedFilesInRevisions() {
        $revs_list = $this->getRevisionsListFromString($this->getCfg('revisions'));

        if ($this->getCfg('verbose')) {
            echo $this->formatString('! check ' . count($revs_list) . " revision(s)", '!'), "\n";
        }

        $affected = array (
            'A' => array (),
            'D' => array (),
            'M' => array (),
            'R' => array (),
        );

        foreach ($revs_list as $rev) {
            $_affected = $this->getAffectedFilesInRevision($rev);

            foreach ($_affected['A'] as $path => $info) {

                unset($affected['D'][$path]);
                unset($affected['M'][$path]);
                unset($affected['R'][$path]);

                $affected['A'][$path] = $info;
            }

            foreach ($_affected['D'] as $path => $info) {

                unset($affected['A'][$path]);
                unset($affected['M'][$path]);
                unset($affected['R'][$path]);

                $affected['D'][$path] = $info;
            }

            foreach ($_affected['M'] as $path => $info) {

                unset($affected['D'][$path]);

                if (!isset($affected['A'][$path])) {
                    $affected['M'][$path] = $info;
                }
            }

            foreach ($_affected['R'] as $path => $info) {
                if (!isset($affected['D'][$path])) {
                    $affected['R'][$path] = $info;
                }
            }
        }

        foreach ($affected as $type => $items) {
            ksort($items);
            foreach ($items as $path => $info) {

                $output = array();
                $format = $this->getCfg('format');

                if (in_array('T', $format)) {
                    $output[] = $type;
                }

                if (in_array('R', $format)) {
                    $output[] = str_pad($info['rev'], 7, ' ', STR_PAD_LEFT);
                }

                if (in_array('A', $format)) {
                    $output[] = str_pad($info['author'], 8, ' ', STR_PAD_LEFT);
                }

                if (in_array('P', $format)) {
                    $output[] = $path;
                }

                echo $this->formatString(join('  ', $output), $type), "\n";
            }
        }

        return $affected;
    }

    private function initConfig($used_opts, $defaults) {
        $short = '';
        $long  = array ();

        $used_opts = str_split($used_opts, 1);

        foreach ($used_opts as $i => $o) {
            if (isset($this->opts[$o])) {
                $short .= $o . $this->opts[$o]['val'];
                $long[] = $this->opts[$o]['name'] . $this->opts[$o]['val'];
            } else {
                unset($used_opts[$i]);
            }
        }

        $opts = getopt($short, $long);


        foreach ($used_opts as $o) {
            $handler = '_' . __FUNCTION__ . ucfirst($this->opts[$o]['name']);
            if (!method_exists($this, $handler)) {
                throw new Exception("No handler defined for {$this->opts[$o]['name']} option (method $handler not found)");
            }

            $this->opts[$this->opts[$o]['name']] = call_user_func(array (&$this, $handler),
                                                                  isset($opts[$o]) ? $opts[$o] : null,
                                                                  isset($opts[$this->opts[$o]['name']]) ? $opts[$this->opts[$o]['name']] : null,
                                                                  isset($defaults[$o]) ? $defaults[$o] : null);
        }
    }

    private function __initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default) {
        return (!empty($short)
            ? $short
            : (!empty($long)
                ? $long
                : $default
            )
        );
    }

    private function _initConfigUser($short, $long, $default) {
        return (!empty($short)
            ? $short
            : (!empty($long)
                ? $long
                : (!empty($default)
                    ? $default
                    : getenv('SVN_USERNAME')
                )
            )
        );
    }

    private function _initConfigPassword($short, $long, $default) {
        return (!empty($short)
            ? $short
            : (!empty($long)
                ? $long
                : (!empty($default)
                    ? $default
                    : getenv('SVN_PASSWORD')
                )
            )
        );
    }

    private function _initConfigTarget($short, $long, $default) {
        return (!empty($short)
            ? $short
            : (!empty($long)
                ? $long
                : (!empty($default)
                    ? $default
                    : getenv('SVN_TARGET')
                )
            )
        );
    }

    private function _initConfigFormat($short, $long, $default) {

        $format = !is_null($short)
            ? $short
            : $long;

        if (is_bool($format)) {
            $format = $default;
        } elseif (!is_string($format)) {
            $format = 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z';
        }

        $format = preg_replace('/[^A-Z,]/', '', $format);

        return explode(',', $format);
    }

    private function _initConfigColored($short, $long, $default) {
        return posix_isatty(STDOUT)
            ? (null !== $short || null !== $long)
            : $default;
    }

    private function _initConfigVerbose($short, $long, $default) {
        return (null !== $short || null !== $long) || $default;
    }

    private function _initConfigDebug($short, $long, $default) {
        return (null !== $short || null !== $long) || $default;
    }

    private function _initConfigRevisions($short, $long, $default) {
        $revs = (!empty($short)
            ? $short
            : (!empty($long)
                ? $long
                : $default
            )
        );

        if (empty($revs)) {
            $revs = stream_get_contents(fopen("php://stdin", "r"));
            $revs = preg_replace('/\s+/', ',', $revs);
        }

        if (empty($revs)) {
            $revs = 'HEAD';
        }

        return $revs;
    }

    private function _initConfigAuthor($short, $long, $default) {
        return $this->__initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default);
    }

    private function _initConfigMessage($short, $long, $default) {
        return $this->__initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default);
    }

    private function _initConfigBetween($short, $long, $default) {
        return $this->__initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default);
    }

    private function _initConfigStop($short, $long, $default) {
        return $this->__initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default);
    }

    private function _initConfigHelp($short, $long, $default) {
        return $this->__initConfigNonEmptyShortOrLongOrDefaultIf($short, $long, $default);
    }

    /**
     * Get config value
     *
     * @param string $name Config name
     *
     * @return mixed Config value
     */
    private function getCfg($name) {
        return isset($this->opts[$name])
            ? $this->opts[$name]
            : null;
    }

    private function formatString($str, $theme = '') {
        if (!empty($theme) && isset($this->colors[$theme]) && $this->getCfg('colored')) {
            $str = $this->c->{$this->colors[$theme]}($str);
        }

        return $str;
    }

    public function printDebug($str, $add_new_line = true) {
        if ($this->getCfg('debug') && $this->getCfg('verbose')) {
            echo $this->formatString($str, 'debug', $add_new_line), ($add_new_line ? "\n" : '');
        }
    }

    private function getRevisionsListFromString($str) {
        $str = str_ireplace('HEAD', $this->info['revision'], $str);
        $str = preg_replace('/[^0-9:,]/', '', $str);
        $str = explode(',', $str);

        $revs = array ();

        foreach ($str as $s) {
            if (empty($s)) {
                continue;
            }

            $range = explode(':', $s); // only 2 first items will be used

            if (count($range) == 2) {
                if ($range[0] > $range[1]) {
                    list ($range[1], $range[0]) = array ($range[0], $range[1]);
                }

                // max revision number should be less or equal to the head
                if ($range[1] > $this->info['revision']) {
                    $range[1] = $this->info['revision'];
                }

                $range[1]++;
                for ($i = $range[0]; $i < $range[1]; $revs[$i] = $i, $i++) ;
            } else {
                if ($range[0] > $this->info['revision']) {
                    $range[0] = $this->info['revision'];
                }
                $revs[$range[0]] = (int)$range[0];
            }
        }

        return $revs;
    }

    private function getRevisionLog($rev) {
        $log = svn_log($this->info['url'], $rev); //, $rev_start, $rev_end);

        if (false === $log) {
            $this->formatString('Auth error', 'err');
            die();
        }

        return $log[0];
    }

    private function getAffectedFilesInRevision($rev) {
        $affected = array (
            'A' => array (),
            'D' => array (),
            'M' => array (),
            'R' => array (),
        );

        $log = $this->getRevisionLog($rev);

        if (empty($log['paths'])) {
            return $affected;
        }

        foreach ($log['paths'] as $path) {
            $path['path'] = str_replace($this->info['prefix'], '', $path['path']);

            if (empty($path['path'])) {
                continue;
            }

            switch ($path['action']) {
                case 'A':
                case 'D':
                case 'M':
                case 'R': // props changed
                    $affected[$path['action']][$path['path']] = array ('author' => $log['author'], 'rev' => $log['rev']);
                    break;
                default:
                    echo $this->formatString("ERROR: UNKNOWN STATUS: " . $path['action'] . ", path: " . $path['path'], 'err'), "\n";
                    die();
            }
        }

        if ($this->getCfg('verbose')) {
            $str = '!' . str_pad($rev, 8, ' ', STR_PAD_LEFT) . ' ' . str_pad($log['author'], 8, ' ', STR_PAD_LEFT) . '   ';

            foreach ($affected as $type => $paths) {
                $str .= $type . ': ' . str_pad(count($paths), 5, ' ') . ' ';
            }

            echo $this->formatString($str . "\n", '!');
        }

        return $affected;
    }

    private function getDateTimeIntervalFromString($interval) {
        if (false === strpos($interval, '~')) {
            $interval .= '~';
        }

        $parts = explode('~', $interval);

        foreach ($parts as &$p) {
            try {
                $p = new DateTime($p);
            } catch (Exception $e) {
                $this->formatString('Invalid DateTime string: ' . $p, 'err');
                die();
            }
        }
        if ($parts[1] < $parts[0]) {
            list ($parts[1], $parts[0]) = $parts;
        }
        return $parts;
    }

}
