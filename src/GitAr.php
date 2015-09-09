<?php namespace wegewerk\devops;

use Garden\Cli\Cli;
use Seld\CliPrompt\CliPrompt;
use PHPGit\Git;
use TheSeer\DirectoryScanner\DirectoryScanner;

/**
* GitAr.
*/
class GitAr
{
    function __construct($argv)
    {
        // Define the cli options.
        $cli = new Cli();

        $cli->description('A simple way to archive and include git repositories in another git repository.')
            // Define the first command: dev.
            ->command('archive')
            ->description('Archive all .git in sub-directories.')
            // Define the first command: int.
            ->command('restore')
            ->description('Archive all .git in sub-directories.')
            ->command('*')
            ->opt('directory:d', 'Root directory for processing.', false, 'string');

        $cliArgs = $cli->parse($argv, true);
        $this->process($cliArgs);
    }

    function process($cliArgs)
    {
        $this->command   = $cliArgs->getCommand();
        $this->directory = $cliArgs->getOpt('directory', getcwd());
        $this->status_file = $this->directory ."/.GitAr.lockfile";

        if(is_dir($this->directory) === FALSE) {
            print("That's not a directory, silly!". PHP_EOL);
            exit(500);
        }

        $method_name = $this->command;
        if(method_exists($this, $method_name)) {
            call_user_func_array(array($this, $method_name), array());
            exit(0);
        }
        else {
            print("Unimplemented command.". PHP_EOL);
            exit(501);
        }
    }

    function getStatus() {
        if(file_exists($this->status_file)) {
            return file_get_contents($this->status_file);
        }
        else {
            return $this->setStatus('restored');
        }
    }

    function setStatus($status) {
        file_put_contents($this->status_file, $status);
        return $status;
    }

    function archive() {
        if($this->getStatus() === "restored") {

          $scanner = new DirectoryScanner();
          $scanner->addExclude($this->directory .'/.git/HEAD');
          $scanner->addInclude('*.git/HEAD');
          $scanner->setFollowSymlinks(FALSE);
          $renames = array();
          foreach($scanner($this->directory) as $info) {
            $oldname = $info->getPath();
            $renames[$oldname] = substr($oldname, 0, -4) .'.GitAr';
          }
          // Must be unset otherwise errors occur during rename();
          unset($scanner);

          $this->batchRename($renames);
          $this->setStatus("archived");
        }
        else {
          print "Cannot archive, because GitAr status is ". $this->getStatus() .".". PHP_EOL;
        }
    }

    function restore() {
        if($this->getStatus() === "archived") {
          $scanner = new DirectoryScanner();
          $scanner->addInclude('*.GitAr/HEAD');
          $scanner->setFollowSymlinks(FALSE);
          $renames = array();
          foreach($scanner($this->directory) as $info) {
              $oldname = $info->getPath();
              $renames[$oldname] = substr(dirname($info), 0, -6) .'.git';
          }
          // Must be unset otherwise errors occur during rename();
          unset($scanner);

          $this->batchRename($renames, "restoring");
          $this->setStatus("restored");
        }
        else {
          print "Cannot restore, because GitAr status is ". $this->getStatus() .".". PHP_EOL;
        }
    }

    function batchRename($renames, $status = "archiving") {
        $this->setStatus($status);
        foreach ($renames as $oldname => $newname) {
            print(ucfirst($status) ." $oldname". PHP_EOL);
            rename($oldname, $newname);
        }
    }
}