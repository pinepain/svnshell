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
    private $info = array ();
    private $c;
    private $colors = array (
        'A' => 'green',
        'D' => 'red',
        'M' => 'blue',
        'R' => 'cyan',
        '!' => 'dark',
        'found_rev' => 'red',
        'found_author' => 'green',
        'found_date' => 'blue',
        'found_msg' => 'white',
        'err' => 'bold_red',
        'debug' => 'yellow',
    );

    public function __construct($user = null, $pswd = null, $repo = null) {
        $repo = empty($repo) ? '.' : $repo;

        if (COLORED) {
            $this->c = new ANSIColor();
        }

        // get info about subversion copy
        $command = 'svn info --xml --non-interactive --no-auth-cache';

        if ($user) {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $user);
            $command .= ' --username ' . $user;
        }

        if ($pswd) {
            svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $pswd);
            $command .= ' --password ' . $pswd;
        }


        $command .= ' ' . $repo;
        $this->printDebug("# username: $user  password: $pswd  target: $repo");
        $this->printDebug("# command: $command");

        $output = shell_exec($command);

        $parser = xml_parser_create();

        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $output, $values, $tags);
        xml_parser_free($parser);

        if (!isset($tags['url'])) {
            $this->printString("Could not get repository info", 'err');
            die();
        }

        $this->info = array (
            'url' => $values[$tags['url'][0]]['value'],
            'root' => $values[$tags['root'][0]]['value'],
            'revision' => $values[$tags['commit'][0]]['attributes']['revision'],
        );

        $this->info['prefix'] = str_replace($this->info['root'], '', $this->info['url']);

        if (VERBOSE) {
            $str = '! ' . $this->info['url'] . '@' . $this->info['revision'];
            $this->printString($str, '!');
        }
    }

    private function printString($str, $theme = '', $add_new_line = true) {
        if (!empty($theme) && isset($this->colors[$theme]) && COLORED) {
            $str = $this->c->{$this->colors[$theme]}($str);
        }

        echo $str, $add_new_line ? "\n" : '';
    }

    public function printDebug($str, $add_new_line = true) {
        if (DEBUG && VERBOSE) {
            $this->printString($str, 'debug', $add_new_line);
        }
    }

    public function getRevisionsListFromString($str) {
        $str = str_ireplace('HEAD', $this->info['revision'], $str);
        $str = preg_replace('/[^0-9:,]/', '', $str);
        $str = explode(',', $str);

        $revs = array ();

        foreach ($str as $s) {
            if (empty($s)) {
                break;
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

    public function showAffectedFilesInRevisionsList(array $revs_list = array ()) {
        if (VERBOSE) {
            $str = '! check ' . count($revs_list) . " revision(s)";
            $this->printString($str, '!');
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
                $str = $type . ' ' . str_pad($info['rev'], 7, ' ', STR_PAD_LEFT) . ' ' . str_pad($info['author'], 8, ' ', STR_PAD_LEFT) . '   ' . $path;

                $this->printString($str, $type);
            }
        }

        return $affected;
    }


    private function getRevisionLog($rev) {
        $log = svn_log($this->info['url'], $rev); //, $rev_start, $rev_end);

        if (false === $log) {
            $this->printString('Auth error', 'err');
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
//                echo Painter::paint("ERROR: EMPTY PATH: $_status, path: $path\n", 'red');
//                die();
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
                    $this->printString("ERROR: UNKNOWN STATUS: " . $path['action'] . ", path: " . $path['path'], 'err');
                    die();
            }
        }

        if (VERBOSE) {
            $str = '!' . str_pad($rev, 8, ' ', STR_PAD_LEFT) . ' ' . str_pad($log['author'], 8, ' ', STR_PAD_LEFT) . '   ';

            foreach ($affected as $type => $paths) {
                $str .= $type . ': ' . str_pad(count($paths), 5, ' ') . ' ';
            }

            $this->printString($str, '!');
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
                $this->printString('Invalid DateTime string: ' . $p, 'err');
                die();
            }
        }
        if ($parts[1] < $parts[0]) {
            list ($parts[1], $parts[0]) = $parts;
        }
        return $parts;
    }

    public function showMatchedRevisions($message, $author, $stop_rev, $between) {
        $between = $this->getDateTimeIntervalFromString($between);

        $start_rev = $this->info['revision'];

        $revs     = $this->getRevisionsListFromString($stop_rev);
        $stop_rev = array_shift($revs);

        if ($stop_rev > $start_rev) {
            $this->printString('Invalid revision number', 'err');
        }

        if (VERBOSE) {
            $str = '! from: ' . str_pad($start_rev, 8, ' ', STR_PAD_RIGHT) . ' to: ' . str_pad($stop_rev, 8, ' ', STR_PAD_RIGHT)
                . '  between: ' . $between[0]->format(DateTime::RFC1036) . ' and: ' . $between[1]->format(DateTime::RFC1036)
                . ' message: ' . $message . ' author: ' . $author;
            $this->printString($str, '!');
        }

        $revs = $this->getRevisionsListFromString($stop_rev . ':' . $start_rev);

        $revs = array_reverse($revs);

        foreach ($revs as $rev) {
            $this->printDebug('# check ' . $rev . ': ', false);

            $info = $this->getRevisionLog($rev);


            if (!$info) {
                $this->printDebug('no commits under given url in this revision');
                continue;
            }

            $info['date'] = new DateTime($info['date']);

//            var_dump($info['date'], $between, $info['date'] > $between[1]);

            if ($info['date'] > $between[1]) {
                $this->printDebug('date ' . $info['date']->format(DateTime::RFC1036) . ' greater than end date ' . $between[1]->format(DateTime::RFC1036));
                continue;
            }

            if ($info['date'] < $between[0]) {
                $this->printDebug('date ' . $info['date']->format(DateTime::RFC1036) . ' less than start date ' . $between[0]->format(DateTime::RFC1036));
                return;
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

            $this->printString('r '.str_pad($info['rev'], 7, ' ', STR_PAD_LEFT), 'found_rev', false);
            $this->printString(' ', null, false);
            $this->printString(str_pad($info['author'], 7, ' ', STR_PAD_LEFT), 'found_author', false);
            $this->printString('  ', null, false);
            $this->printString($info['date'], 'found_date', false);
            $this->printString('  ', null, false);
            $this->printString($info['msg'], 'found_msg');
        }
    }
}
