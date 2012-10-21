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

    protected function configure()
    {
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


        $dir = $this->getContainer()->getParameter('vimeo_dir');

        $excludeArr = array('.DS_Store','@eaDir','.','..');
        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if(in_array($entry, $excludeArr)) continue;
                echo "$sessionId: Uploading: $dir/$entry\n";    
                try {
                    $this->upload("$dir/$entry");
                    $this->delete("$dir/$entry");
                    $logger->info("$sessionId: SUCCESS: $entry");
                } catch (Exception $e) {
                    $logger->error("$sessionId: EXCEPTION: ".$e->getMessage());
                }
                
            }

            closedir($handle);
        }
        
        
        $this->unlock();

    }

    protected function upload($path){
        $tmpDir = $this->getApplication()->getKernel()->getCacheDir();

        $video = new Vimeo();
        $video->setFilename($path);


        $consumerKey = $this->getContainer()->getParameter('vimeo_consumer_key');
        $consumerSecret = $this->getContainer()->getParameter('vimeo_consumer_secret');
        $accessToken = $this->getContainer()->getParameter('vimeo_access_token');
        $accessTokenSecret = $this->getContainer()->getParameter('vimeo_access_token_secret');

        $vimeoService = new \Vimeo_Vimeo($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
        $vimeoService->setTmpDir($tmpDir);
        $video_id = $video->upload($vimeoService);
    }

    protected function delete($path){
        try {
            unlink($path);  
        } catch (Exception $e) {
            echo "Unable to delete file $path :". $e->getMessage();
        }
    }
}