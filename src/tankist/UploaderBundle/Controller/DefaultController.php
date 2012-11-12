<?php

namespace tankist\UploaderBundle\Controller;
use tankist\UploaderBundle\Entity\Vimeo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="_landing")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/list/", name="_list")
     * @Template()
     */
    public function listAction()
    {
        $excludeFiles = array('.','..','.DS_Store','@eaDir');

        $dir = $this->container->getParameter('video_directory');
		$dh  = opendir($dir);

        $vimeoRepository = $this->getDoctrine()->getRepository('tankistUploaderBundle:Vimeo');
        $vimeoVideos = $vimeoRepository->findAll();

        $files = array();
		while (false !== ($filename = readdir($dh))) {
            if(!in_array($filename,$excludeFiles)){
                //$vimeoVideo = $vimeoVideos->findOneBy(array('filename' => $filename));
		        $files[] = array(
                    'name' => $filename,
                    'vimeo' => $this->findVideoByFilename($vimeoVideos,$filename)
                );
            }
		}

        usort($files, function($a,$b){
            if ($a['name'] == $b['name']) {
                return 0;
            } else {
                return $a['name'] < $b['name'] ? 1 : -1; // reverse order
            }
        });

		return new Response(json_encode(array('files' => $files)));
    }

    protected function findVideoByFilename($resultSet, $filename){
        foreach($resultSet as $videoEntity){
            if($videoEntity->getFilename() == $filename){
                return array(
                    'id'        => $videoEntity ? $videoEntity->getVimeoId() : 0,
                    'status'    => $videoEntity ? $videoEntity->getStatus()  : 'not uploaded',
                );
            }
        }
        return array();
    }

    /**
     * @Route("/tail", name="_tail")
     * @Template()
     */
    public function tailAction()
    {
        $filesToBeWatched = array(
            'youtube.log','youtube.output.log',
            'vimeo.log','vimeo.output.log'
            );

        $logDir = $this->get('kernel')->getLogDir();
        $data = array();        
        foreach($filesToBeWatched as $file) {
            $data[$file] = shell_exec('tail -n 50 '.$logDir.'/'.$file);
        }

        //echo exit;
        //echo exec('tail -n 50 '.$cacheDir.'/appdevUrlMatcher.php.meta');exit;
        return new Response(json_encode(array($data)));
    }

}
