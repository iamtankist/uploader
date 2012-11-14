<?php
namespace tankist\UploaderBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use tankist\UploaderBundle\Entity\Vimeo;

class VimeoUploadCommand extends ContainerAwareCommand
{

    protected $lockfle = '';

    protected $service = '';

    protected function configure(){
        $this
            ->setName('upload:vimeo')
            ->setDescription('Uploads file to vimeo');
        ;
    }

    protected function lock(){
        fopen($this->lockfile, "w");
    }

    protected function isLocked(){
        return file_exists($this->lockfile);
    }

    protected function unlock(){
        unlink($this->lockfile);    
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sessionId = md5(time());
        $this->lockfile = $this->getApplication()->getKernel()->getCacheDir()."/".str_replace('upload:','',$this->getName()).".lock";

        if($this->isLocked()) {
            throw new \Exception('Command is still being executed');
        }
        $this->lock();

        // create a log channel
        $logDir = $this->getApplication()->getKernel()->getLogDir();
        $logfile = $logDir."/vimeo.log";
        $logger = new Logger('uploadLogger');
        $logger->pushHandler(new StreamHandler($logfile, Logger::INFO));
        
        try {
            $this->connect();
        } catch (Exception $e) {
            $logger->error("$sessionId: EXCEPTION: ".$e->getMessage());
            $this->unlock();
            exit;           
        }

        $dir = $this->getContainer()->getParameter('vimeo_dir');

        $excludeArr = array('.DS_Store','@eaDir','.','..');
        $files = array();
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if(in_array($entry, $excludeArr)) continue;
                $files[] = $entry;              
            }

            closedir($handle);
        }
        
        if(empty($files) || !$this->checkQuota()) { 
            $this->unlock(); exit;
        }

        sort($files);

        foreach($files as $entry) {
            echo "Uploading: $dir/$entry\n";    
            try {
                $this->upload("$dir/$entry");
                $this->delete("$dir/$entry");
                $logger->info("$sessionId: SUCCESS: $entry");
            } catch (Exception $e) {
                $logger->error("$sessionId: EXCEPTION: ".$e->getMessage());
            }
            break; //only one file per cron iteration. due to some vimeo internal errors
        }

        $this->unlock();
    }

    protected function connect(){
        $tmpDir = $this->getApplication()->getKernel()->getCacheDir();
        $consumerKey = $this->getContainer()->getParameter('vimeo_consumer_key');
        $consumerSecret = $this->getContainer()->getParameter('vimeo_consumer_secret');
        $accessToken = $this->getContainer()->getParameter('vimeo_access_token');
        $accessTokenSecret = $this->getContainer()->getParameter('vimeo_access_token_secret');

        $this->service = new \Vimeo_Vimeo($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
        $this->service->setTmpDir($tmpDir);
    }

    protected function checkQuota(){
        $quota = $this->service->call('vimeo.videos.upload.getQuota');
        return $quota->user->upload_space->free > 1024*1024*1024;
    }

    protected function upload($path){
        $video = new Vimeo();
        $video->setFilename($path);
        $video_id = $video->upload($this->service);
        if ($video_id) {
            $str = 'Success https://vimeo.com/' . $video_id;
        } else {
            $str = "Not able to retrieve the video status information yet. " . 
              "Please try again later.";
        }

        exec("php app/console notify:gtalk 'Vimeo: $str'");
    }

    protected function delete($path){
        try {
            unlink($path);  
        } catch (Exception $e) {
            echo "Unable to delete file $path :". $e->getMessage();
        }
    }
}