<?php
namespace tankist\UploaderBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use tankist\UploaderBundle\Entity\Vimeo;

class VimeoUploadCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('vimeo:upload')
            ->setDescription('Uploads file to vimeo')
            ->addArgument('path', InputArgument::OPTIONAL, 'Which file do you want to upload?')
            ->addOption('yell', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters')
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

        try {
            $video_id = $video->upload($tmpDir,$logger);
            if($video_id) {
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