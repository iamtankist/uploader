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

//use tankist\UploaderBundle\Entity\Vimeo;

class YoutubeUploadCommand extends ContainerAwareCommand {

	protected $yt ='';

	protected $lockfle = '';

	protected function configure() {
		
        $this
            ->setName('upload:youtube')
            ->setDescription('Uploads file to youtube')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
    	$sessionId = md5(time());
		$this->lockfile = $this->getApplication()->getKernel()->getCacheDir()."/".str_replace('upload:','',$this->getName()).".lock";

		if($this->isLocked()) {
			throw new \Exception('Command is still being executed');
		}
		$this->lock();


		// create a log channel
        $logDir = $this->getApplication()->getKernel()->getLogDir();
        $logfile = $logDir."/youtube.log";
        $logger = new Logger('uploadLogger');
        $logger->pushHandler(new StreamHandler($logfile, Logger::INFO));


		try {
			$this->connect();
		} catch (Exception $e) {
			$logger->error("$sessionId: EXCEPTION: ".$e->getMessage());
			$this->unlock();
			exit;			
		}

		

		$dir = $this->getContainer()->getParameter('youtube_dir');

		$excludeArr = array('.DS_Store','@eaDir','.','..');
		$files = array();
		if ($handle = opendir($dir)) {
		    while (false !== ($entry = readdir($handle))) {
		    	if(in_array($entry, $excludeArr)) continue;
				$files[] = $entry;		    	
		    }

		    closedir($handle);
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
                $this->unlock();
				exit;
            }
		}

		$this->unlock();
	}

	protected function connect(){
		
    	$login    = $this->getContainer()->getParameter('youtube_account');
        $password = $this->getContainer()->getParameter('youtube_password');
        $appName  = $this->getContainer()->getParameter('youtube_app');
        $devKey   = $this->getContainer()->getParameter('youtube_dev_key');
        
        $authenticationURL= 'https://www.google.com/accounts/ClientLogin';
		$httpClient = 
		  \Zend_Gdata_ClientLogin::getHttpClient(
		              $login,
		              $password,
		              $service = 'youtube',
		              $client = null,
		              $source = $appName, // a short string identifying your application
		              $loginToken = null,
		              $loginCaptcha = null,
		              $authenticationURL);

		$this->yt = new \Zend_Gdata_YouTube($httpClient, $appName, $appName, $devKey);
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

	protected function upload($path){
		$myVideoEntry = new \Zend_Gdata_YouTube_VideoEntry();

		$filename = pathinfo($path, PATHINFO_BASENAME);

		$filesource = $this->yt->newMediaFileSource($path);
		$filesource->setContentType('video/quicktime');
		$filesource->setSlug($filename);
		$myVideoEntry->setMediaSource($filesource);

		$myVideoEntry->setVideoTitle($filename);
		$myVideoEntry->setVideoDescription($filename);
		$myVideoEntry->setVideoCategory('Comedy');
		$myVideoEntry->SetVideoTags('kids, funny');
		$myVideoEntry->setVideoDeveloperTags(array('kids', 'funny', 'family', 'arina', 'max', 'mkrtchyan'));

		$myVideoEntry->setVideoPrivate();

		$uploadUrl = 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads';

		try {
		  $newEntry = $this->yt->insertEntry($myVideoEntry, $uploadUrl, 'Zend_Gdata_YouTube_VideoEntry');
		} catch (Zend_Gdata_App_HttpException $httpException) {
		  echo $httpException->getRawResponseBody();
		} catch (Zend_Gdata_App_Exception $e) {
		    echo $e->getMessage();
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