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
        $this->lockfile = $this->getApplication()->getKernel()->getCacheDir()."/".str_replace('upload:','',$this->getName()).".lock";

        if($this->isLocked()) {
            throw new \Exception('Command is still being executed');
        }
        $this->lock();

        $dir      = $this->getContainer()->getParameter('vimeo_dir');

        if ($handle = opendir($dir)) {
            while (false !== ($entry = readdir($handle))) {
                if($entry == '.' || $entry == '..') continue;
                echo "Uploading: $dir/$entry\n";    
                
                $this->upload("$dir/$entry");
                $this->delete("$dir/$entry");
            }

            closedir($handle);
        }
        
        
        $this->unlock();

    }

    protected function upload($path){
        $tmpDir = $this->getApplication()->getKernel()->getCacheDir();
        $logDir = $this->getApplication()->getKernel()->getLogDir();

        $filename = pathinfo($path, PATHINFO_BASENAME);
        $logfile = $logDir."/log_".$filename.".log";

         // create a log channel
        $logger = new Logger('uploadLogger');
        $logger->pushHandler(new StreamHandler($logfile, Logger::INFO));

        $video = new Vimeo();
        $video->setFilename($path);


        $consumerKey = $this->getContainer()->getParameter('vimeo_consumer_key');
        $consumerSecret = $this->getContainer()->getParameter('vimeo_consumer_secret');
        $accessToken = $this->getContainer()->getParameter('vimeo_access_token');
        $accessTokenSecret = $this->getContainer()->getParameter('vimeo_access_token_secret');
        

        $vimeoService = new \Vimeo_Vimeo($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
        $vimeoService->setLogger($logger);
        $vimeoService->setTmpDir($tmpDir);



        try {
            $video_id = $video->upload($vimeoService);
        }
        catch (VimeoAPIException $e) {
            $text = "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
        }

    }

    protected function delete($path){
        try {
            unlink($path);  
        } catch (Exception $e) {
            echo "Unable to delete file $path :". $e->getMessage();
        }
    }
}