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
    protected function configure()
    {
        $this
            ->setName('upload:vimeo')
            ->setDescription('Uploads file to vimeo')
            ->addArgument('path', InputArgument::REQUIRED, 'Which file do you want to upload?')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
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
            if($video_id) {
                $em = $this->getContainer()->get('doctrine')->getEntityManager();
                $video = $em->getRepository('tankist\UploaderBundle\Entity\Vimeo')->findOneBy(array("filename" => $filename));
                if(!$video){
                    $video = new Vimeo();
                    $video->setFilename($filename);
                }

                $video->setVimeoId($video_id);
                
                $em->persist($video);
                $em->flush();
                $text = '<a href="http://vimeo.com/' . $video_id . '">Upload successful!</a>';
            } else {
                $text = "Video file did not exist!";
            }
        }
        catch (VimeoAPIException $e) {
            $text = "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
        }

        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }

        $output->writeln($text);
    }
}