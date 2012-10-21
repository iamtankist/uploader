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

class UnlockUploadCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('upload:unlock')
            ->setDescription('Unlocks file upload services');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        @unlink($this->getApplication()->getKernel()->getCacheDir()."/youtube.lock");    
        @unlink($this->getApplication()->getKernel()->getCacheDir()."/vimeo.lock");    
    }

}